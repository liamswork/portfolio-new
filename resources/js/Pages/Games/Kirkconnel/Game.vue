<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { renderMap, hitTest, tickAnimations } from '@/game/kirkconnel/renderer.js';
import { PHASE_PLACE, PHASE_ATTACK, PHASE_FORTIFY } from '@/game/kirkconnel/constants.js';

const props = defineProps({
    game:     Object,
    map:      Object,   // { id, name, continents, polygons: [{id, name, continent, vertices, centroid, connections}] }
    players:  Array,
    myPlayer: Object,
    auth:     Object,
});

// ── State ─────────────────────────────────────────────────────────────────────
const canvas      = ref(null);
const gameState   = ref(props.game.state);
const players     = ref(props.players);
const currentTurn = ref(props.game.current_turn);
const gameStatus  = ref(props.game.status);
const round       = ref(props.game.round);
const winnerId    = ref(props.game.winner_id);

const phase       = ref(PHASE_PLACE);
const selected    = ref(null);
const placeAmount = ref(1);
const attackArmy  = ref(1);
const fortifyAmt  = ref(1);
const log         = ref([]);
const sending     = ref(false);
const animations  = ref([]);
const dragAmount  = ref(null);

const PLACE_OPTIONS = [1, 3, 5, 10, 25];

// ── Computed ──────────────────────────────────────────────────────────────────
const isMyTurn = computed(() =>
    gameStatus.value === 'active' && props.myPlayer && currentTurn.value === props.myPlayer.turn_order
);

const myReinforcements = computed(() => {
    if (!props.myPlayer) return 0;
    return players.value.find(p => p.user_id === props.auth.user.id)?.reinforcements ?? 0;
});

const playerColors = computed(() => {
    const map = {};
    for (const p of players.value) map[p.user_id] = p.color;
    return map;
});

const currentPlayerName  = computed(() => players.value.find(p => p.turn_order === currentTurn.value)?.name ?? '?');
const currentPlayerColor = computed(() => players.value.find(p => p.turn_order === currentTurn.value)?.color ?? '#fff');

const selectedPolygon = computed(() => props.map.polygons.find(p => p.id === selected.value) ?? null);
const selectedState   = computed(() => gameState.value?.polygons?.find(p => p.id === selected.value) ?? null);

const myPolygons = computed(() => {
    if (!props.myPlayer) return [];
    return gameState.value?.polygons?.filter(p => p.owner === props.auth.user.id) ?? [];
});

const attackTargets = computed(() => {
    if (!selected.value || phase.value !== PHASE_ATTACK) return [];
    const poly = props.map.polygons.find(p => p.id === selected.value);
    if (!poly) return [];
    return poly.connections.filter(cid => {
        const s = gameState.value?.polygons?.find(p => p.id === cid);
        return s && s.owner !== props.auth.user.id;
    });
});

const fortifyTargets = computed(() => {
    if (!selected.value || phase.value !== PHASE_FORTIFY) return [];
    const poly = props.map.polygons.find(p => p.id === selected.value);
    if (!poly) return [];
    return poly.connections.filter(cid => {
        const s = gameState.value?.polygons?.find(p => p.id === cid);
        return s && s.owner === props.auth.user.id;
    });
});

const highlightIds = computed(() => {
    if (phase.value === PHASE_ATTACK)  return attackTargets.value;
    if (phase.value === PHASE_FORTIFY) return fortifyTargets.value;
    if (phase.value === PHASE_PLACE && isMyTurn.value) return myPolygons.value.map(p => p.id);
    return [];
});

// ── Canvas render loop ────────────────────────────────────────────────────────
let raf = null;

function draw() {
    const ctx = canvas.value?.getContext('2d');
    if (!ctx) return;
    animations.value = tickAnimations(animations.value);
    renderMap(
        ctx,
        props.map,
        gameState.value,
        props.auth.user.id,
        selected.value,
        highlightIds.value,
        phase.value === PHASE_ATTACK ? attackTargets.value : [],
        playerColors.value,
        animations.value,
    );
    raf = requestAnimationFrame(draw);
}

onMounted(() => {
    raf = requestAnimationFrame(draw);
    setupEcho();
    if (props.myPlayer?.session_token) {
        localStorage.setItem(`kirkconnel_token_${props.game.id}`, props.myPlayer.session_token);
    }
});

onUnmounted(() => {
    cancelAnimationFrame(raf);
    window.Echo?.leave(`kirkconnel.game.${props.game.id}`);
});

// ── Reverb / Echo ─────────────────────────────────────────────────────────────
function setupEcho() {
    window.Echo.join(`kirkconnel.game.${props.game.id}`)
        .here(members => addLog(`${members.length} player(s) connected.`))
        .joining(member => addLog(`${member.name} joined.`))
        .leaving(member => addLog(`${member.name} disconnected.`))
        .listen('.game.updated', applyUpdate);
}

function spawnAttackAnimation(fromId, toId) {
    const from = props.map.polygons.find(p => p.id === fromId);
    const to   = props.map.polygons.find(p => p.id === toId);
    if (!from || !to) return;
    animations.value.push({
        fromX: from.centroid.x, fromY: from.centroid.y,
        toX:   to.centroid.x,   toY:   to.centroid.y,
        progress: 0, success: true,
    });
}

function applyUpdate(data) {
    if (data.game.state && gameState.value) {
        const prev = gameState.value.polygons;
        const next = data.game.state.polygons;
        if (prev && next) {
            for (const np of next) {
                const pp = prev.find(p => p.id === np.id);
                if (pp && pp.owner !== np.owner && np.owner !== null) {
                    const mapPoly = props.map.polygons.find(p => p.id === np.id);
                    const attacker = mapPoly?.connections.find(cid => {
                        const s = next.find(p => p.id === cid);
                        return s?.owner === np.owner;
                    });
                    spawnAttackAnimation(attacker ?? np.id, np.id);
                }
            }
        }
    }

    const prevTurn = currentTurn.value;
    gameState.value   = data.game.state;
    currentTurn.value = data.game.current_turn;
    gameStatus.value  = data.game.status;
    round.value       = data.game.round;
    winnerId.value    = data.game.winner_id;
    players.value     = data.players;

    const justBecameMyTurn = prevTurn !== data.game.current_turn
        && props.myPlayer
        && data.game.current_turn === props.myPlayer.turn_order;

    if (justBecameMyTurn) {
        phase.value    = PHASE_PLACE;
        selected.value = null;
        addLog('Your turn! Place your reinforcements.');
    }
}

function addLog(msg) {
    log.value.unshift({ msg, time: new Date().toLocaleTimeString() });
    if (log.value.length > 30) log.value.pop();
}

// ── Canvas interaction ────────────────────────────────────────────────────────
function onCanvasClick(e) {
    if (!isMyTurn.value) return;
    const rect   = canvas.value.getBoundingClientRect();
    const scaleX = canvas.value.width  / rect.width;
    const scaleY = canvas.value.height / rect.height;
    const x = (e.clientX - rect.left) * scaleX;
    const y = (e.clientY - rect.top)  * scaleY;

    const pid = hitTest(x, y, props.map.polygons);
    if (!pid) { selected.value = null; return; }

    const pState = gameState.value?.polygons?.find(p => p.id === pid);

    if (phase.value === PHASE_PLACE) {
        if (pState?.owner === props.auth.user.id) {
            if (selected.value === pid) doPlace();
            else selected.value = pid;
        }
        return;
    }

    if (phase.value === PHASE_ATTACK) {
        if (!selected.value) {
            if (pState?.owner === props.auth.user.id && pState?.armies > 1) selected.value = pid;
        } else if (attackTargets.value.includes(pid)) {
            doAttack(selected.value, pid);
        } else if (pState?.owner === props.auth.user.id) {
            selected.value = pid;
        } else {
            addLog(`${props.map.polygons.find(p => p.id === pid)?.name} is not adjacent.`);
        }
        return;
    }

    if (phase.value === PHASE_FORTIFY) {
        if (!selected.value) {
            if (pState?.owner === props.auth.user.id && pState?.armies > 1) selected.value = pid;
        } else if (fortifyTargets.value.includes(pid)) {
            doFortify(selected.value, pid);
        } else {
            selected.value = pState?.owner === props.auth.user.id ? pid : null;
        }
    }
}

// ── Actions ───────────────────────────────────────────────────────────────────
async function sendAction(payload) {
    sending.value = true;
    try {
        const res = await window.axios.post(route('kirkconnel.action', props.game.id), payload);
        if (res.data.game) applyUpdate(res.data);
    } catch (err) {
        addLog('Error: ' + (err.response?.data?.message ?? 'Unknown error'));
    } finally {
        sending.value = false;
    }
}

function doPlace(amount) {
    const amt = amount ?? placeAmount.value;
    if (!selected.value || myReinforcements.value < amt) return;
    sendAction({ type: 'place', polygon_id: selected.value, armies: amt });
    addLog(`Placed ${amt} on ${selectedPolygon.value?.name}`);
}

function doAttack(from, to) {
    const fromState = gameState.value?.polygons?.find(p => p.id === from);
    const armies    = Math.min(attackArmy.value, (fromState?.armies ?? 1) - 1);
    spawnAttackAnimation(from, to);
    sendAction({ type: 'attack', from, to, armies });
    addLog(`Attacking ${props.map.polygons.find(p => p.id === to)?.name}...`);
    selected.value = null;
}

function doFortify(from, to) {
    sendAction({ type: 'fortify', from, to, armies: fortifyAmt.value });
    addLog(`Fortifying ${props.map.polygons.find(p => p.id === to)?.name}`);
    selected.value = null;
}

function endTurn() {
    sendAction({ type: 'endturn' });
    phase.value    = PHASE_PLACE;
    selected.value = null;
    addLog('Turn ended.');
}

function startGame() {
    router.post(route('kirkconnel.start', props.game.id));
}

function onDragStart(e, amount) {
    dragAmount.value = amount;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', amount);
}

function onCanvasDrop(e) {
    if (!isMyTurn.value || phase.value !== PHASE_PLACE || dragAmount.value === null) return;
    e.preventDefault();
    const rect   = canvas.value.getBoundingClientRect();
    const scaleX = canvas.value.width  / rect.width;
    const scaleY = canvas.value.height / rect.height;
    const x = (e.clientX - rect.left) * scaleX;
    const y = (e.clientY - rect.top)  * scaleY;
    const pid = hitTest(x, y, props.map.polygons);
    if (!pid) { dragAmount.value = null; return; }
    const pState = gameState.value?.polygons?.find(p => p.id === pid);
    if (pState?.owner === props.auth.user.id && myReinforcements.value >= dragAmount.value) {
        selected.value = pid;
        doPlace(dragAmount.value);
    }
    dragAmount.value = null;
}

function onCanvasDragOver(e) {
    if (isMyTurn.value && phase.value === PHASE_PLACE) e.preventDefault();
}
</script>

<template>
    <AppLayout title="Kirkconnel">
        <Head title="Kirkconnel" />
        <div class="max-w-screen-xl mx-auto px-8 md:px-16 py-6" style="color:#f9fafb; min-height:1000px; display:flex; flex-direction:column">

            <!-- Header bar -->
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1 class="text-2xl font-bold uppercase">Kirkconnel</h1>
                    <p style="color:rgba(255,255,255,0.4); font-size:0.875rem">{{ map.name }} &nbsp;·&nbsp; Round {{ round }}</p>
                </div>
                <div class="flex gap-2">
                    <div
                        v-for="p in players" :key="p.id"
                        class="flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border"
                        :style="{
                            borderColor: p.color, color: p.color,
                            backgroundColor: p.color + '22',
                            opacity: p.eliminated ? 0.35 : 1,
                            outline: p.turn_order === currentTurn ? `2px solid ${p.color}` : 'none',
                        }"
                    >
                        <span class="w-2 h-2 rounded-full inline-block" :style="{ backgroundColor: p.connected ? '#22c55e' : '#6b7280' }"></span>
                        {{ p.name }}
                        <span v-if="p.turn_order === currentTurn">▶</span>
                    </div>
                </div>
            </div>

            <!-- Game over banner -->
            <div v-if="gameStatus === 'finished'" class="mb-4 p-4 rounded-lg text-center" style="background:rgba(233,79,55,0.2); border:1px solid #e94f37">
                <p class="text-xl font-bold">{{ players.find(p => p.user_id === winnerId)?.name ?? 'Unknown' }} wins!</p>
            </div>

            <!-- Waiting to start -->
            <div v-if="gameStatus === 'waiting'" class="mb-4 p-4 rounded-lg text-center" style="background:#1f2937">
                <p style="color:rgba(255,255,255,0.6)" class="mb-3">Waiting for players... ({{ players.length }} / {{ game.max_players }})</p>
                <button
                    v-if="auth.user.id === game.created_by"
                    @click="startGame"
                    :disabled="players.length < 1"
                    class="px-6 py-2 rounded font-bold hover:opacity-80 disabled:opacity-40"
                    style="background:#e94f37; color:#fff"
                >Start Game</button>
                <p v-else style="color:rgba(255,255,255,0.4); font-size:0.875rem">Waiting for host to start...</p>
            </div>

            <div class="flex gap-6 items-start">
                <!-- Canvas map -->
                <div class="relative flex-1">
                    <canvas
                        ref="canvas"
                        width="680"
                        height="520"
                        class="block rounded-lg cursor-pointer"
                        style="width:100%; background:#111827;"
                        @click="onCanvasClick"
                        @drop="onCanvasDrop"
                        @dragover="onCanvasDragOver"
                    />
                </div>

                <!-- Sidebar -->
                <div class="w-56 flex flex-col gap-4 shrink-0" style="color:#f9fafb">

                    <!-- Turn controls -->
                    <div v-if="isMyTurn && gameStatus === 'active'" class="rounded-lg p-4" style="background:#1f2937">
                        <p style="font-size:0.7rem; text-transform:uppercase; font-weight:700; color:rgba(255,255,255,0.5)" class="mb-3">Your Turn</p>

                        <div class="flex rounded overflow-hidden mb-3" style="font-size:0.75rem; font-weight:700">
                            <button
                                v-for="(label, p) in { place: 'Place', attack: 'Attack', fortify: 'Fortify' }"
                                :key="p"
                                @click="phase = p; selected = null"
                                class="flex-1 py-1.5 transition"
                                :style="phase === p ? 'background:#e94f37; color:#fff' : 'background:rgba(255,255,255,0.1); color:rgba(255,255,255,0.6)'"
                            >{{ label }}</button>
                        </div>

                        <!-- Place phase -->
                        <div v-if="phase === PHASE_PLACE">
                            <p style="font-size:0.875rem" class="mb-2">
                                Reinforcements: <span style="font-weight:700; color:#fbbf24">{{ myReinforcements }}</span>
                            </p>
                            <div class="flex flex-wrap gap-1 mb-3">
                                <span
                                    v-for="amt in PLACE_OPTIONS.filter(a => a <= myReinforcements)"
                                    :key="amt"
                                    draggable="true"
                                    @dragstart="onDragStart($event, amt)"
                                    @click="placeAmount = amt"
                                    class="px-2 py-1 rounded cursor-grab select-none font-bold transition"
                                    :style="placeAmount === amt
                                        ? 'background:#fbbf24; color:#111; font-size:0.75rem'
                                        : 'background:rgba(255,255,255,0.12); color:#f9fafb; font-size:0.75rem'"
                                >{{ amt }}</span>
                            </div>
                            <p style="font-size:0.7rem; color:rgba(255,255,255,0.4)" class="mb-2">
                                Click a polygon to select, click again to place. Or drag a chip.
                            </p>
                            <button
                                @click="doPlace()"
                                :disabled="!selected || myReinforcements < placeAmount || sending"
                                class="w-full py-1.5 rounded font-bold disabled:opacity-40 hover:opacity-80"
                                style="background:#e94f37; color:#fff; font-size:0.875rem"
                            >Place {{ placeAmount }} {{ placeAmount === 1 ? 'Army' : 'Armies' }}</button>
                            <button
                                v-if="myReinforcements === 0"
                                @click="phase = PHASE_ATTACK; selected = null"
                                class="w-full mt-2 py-1.5 rounded hover:opacity-80"
                                style="background:rgba(255,255,255,0.1); color:#fff; font-size:0.875rem"
                            >Done Placing →</button>
                        </div>

                        <!-- Attack phase -->
                        <div v-if="phase === PHASE_ATTACK">
                            <p style="font-size:0.75rem; color:rgba(255,255,255,0.5)" class="mb-2">Select your polygon, then click an adjacent enemy.</p>
                            <div v-if="selected">
                                <p style="font-size:0.875rem" class="mb-1">Attacking with:</p>
                                <input type="range" v-model.number="attackArmy" min="1" :max="Math.max(1,(selectedState?.armies??1)-1)" class="w-full mb-1" />
                                <p style="font-size:0.75rem; color:rgba(255,255,255,0.6); text-align:center">{{ attackArmy }} dice</p>
                            </div>
                        </div>

                        <!-- Fortify phase -->
                        <div v-if="phase === PHASE_FORTIFY">
                            <p style="font-size:0.75rem; color:rgba(255,255,255,0.5)" class="mb-2">Move armies between adjacent polygons you own.</p>
                            <div v-if="selected">
                                <input type="range" v-model.number="fortifyAmt" min="1" :max="Math.max(1,(selectedState?.armies??1)-1)" class="w-full mb-1" />
                                <p style="font-size:0.75rem; color:rgba(255,255,255,0.6); text-align:center">Move {{ fortifyAmt }}</p>
                            </div>
                        </div>

                        <button
                            @click="endTurn"
                            :disabled="sending"
                            class="w-full mt-3 py-1.5 rounded hover:opacity-80 disabled:opacity-40"
                            style="background:rgba(255,255,255,0.1); color:#fff; font-size:0.875rem"
                        >End Turn</button>
                    </div>

                    <!-- Spectating -->
                    <div v-if="!isMyTurn && gameStatus === 'active'" class="rounded-lg p-3 text-center" style="background:#1f2937; font-size:0.75rem; color:rgba(255,255,255,0.5)">
                        <span :style="{ color: currentPlayerColor }">{{ currentPlayerName }}</span>'s turn
                    </div>

                    <!-- Selected polygon info -->
                    <div v-if="selectedPolygon" class="rounded-lg p-4" style="background:#1f2937; font-size:0.875rem">
                        <p style="font-weight:700" class="mb-1">{{ selectedPolygon.name }}</p>
                        <p style="color:rgba(255,255,255,0.5); font-size:0.75rem">Armies: {{ selectedState?.armies ?? 0 }}</p>
                        <p style="color:rgba(255,255,255,0.5); font-size:0.75rem">
                            Owner:
                            <span :style="{ color: playerColors[selectedState?.owner] ?? '#9ca3af' }">
                                {{ players.find(p => p.user_id === selectedState?.owner)?.name ?? 'Unowned' }}
                            </span>
                        </p>
                        <p style="color:rgba(255,255,255,0.5); font-size:0.75rem" class="mt-1">Borders: {{ selectedPolygon.connections.length }}</p>
                    </div>

                    <!-- Continent legend -->
                    <div class="rounded-lg p-4" style="background:#1f2937">
                        <p style="font-size:0.7rem; text-transform:uppercase; font-weight:700; color:rgba(255,255,255,0.5)" class="mb-2">Continents</p>
                        <div v-for="c in map.continents" :key="c.id" class="flex items-center justify-between mb-1" style="font-size:0.75rem">
                            <div class="flex items-center gap-1.5">
                                <span class="w-2.5 h-2.5 rounded-sm inline-block" :style="{ backgroundColor: c.color }"></span>
                                <span style="color:#f9fafb">{{ c.name }}</span>
                            </div>
                            <span style="color:#fbbf24">+{{ c.bonus }}</span>
                        </div>
                    </div>

                    <!-- Event log -->
                    <div class="rounded-lg p-4 overflow-y-auto" style="background:#1f2937; max-height:12rem">
                        <p style="font-size:0.7rem; text-transform:uppercase; font-weight:700; color:rgba(255,255,255,0.5)" class="mb-2">Log</p>
                        <div v-for="(entry, i) in log" :key="i" style="font-size:0.75rem; color:rgba(255,255,255,0.6)" class="mb-0.5">
                            <span style="color:rgba(255,255,255,0.3)">{{ entry.time }}</span> {{ entry.msg }}
                        </div>
                        <p v-if="!log.length" style="font-size:0.75rem; color:rgba(255,255,255,0.3)">No events yet.</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
