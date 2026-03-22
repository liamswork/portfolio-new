<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ auth: Object });

// ── State ─────────────────────────────────────────────────────────────────────
const mapName        = ref('');
const mapDescription = ref('');
const continents     = ref([{ id: 'c1', name: 'Continent 1', bonus: 2, color: '#1e3a5f' }]);
const polygons       = ref([]);   // [{id, name, continent, vertices:[{x,y}]}]
const connections    = ref([]);   // [{a: polygonIndex, b: polygonIndex}]

const mode           = ref('draw');   // draw | select | connect | disconnect | delete
const saving         = ref(false);
const saveMsg        = ref('');

// Drawing state
const currentVertices = ref([]);   // vertices being placed for the in-progress polygon
const SNAP_RADIUS     = 12;        // px — snap to first vertex to close polygon

// Connect mode
const connectA = ref(null);   // index into polygons[]

// Select / move state
const selectedIdx  = ref(null);   // index of selected polygon in select mode
const dragging     = ref(false);
const dragStart    = ref(null);   // {x, y} canvas coords where drag began
const dragOrigin   = ref(null);   // copy of vertices at drag start
const overlapWarn  = ref(false);  // true when a drag position would cause overlap

const CONTINENT_COLORS = ['#1e3a5f','#3b1f1f','#1f3b1f','#3b3b1f','#2d1f3b','#1f2d3b'];

const canvas = ref(null);

// ── Computed ──────────────────────────────────────────────────────────────────
const selectedPolygon = computed(() =>
    connectA.value !== null ? polygons.value[connectA.value] : null
);

// ── Keyboard ──────────────────────────────────────────────────────────────────
function onKeyDown(e) {
    if (e.key !== 'Escape') return;
    if (currentVertices.value.length > 0) {
        // Cancel in-progress polygon
        currentVertices.value = [];
        draw();
    } else if (selectedIdx.value !== null) {
        selectedIdx.value = null;
        dragging.value    = false;
        draw();
    } else if (connectA.value !== null) {
        connectA.value = null;
        draw();
    }
}

// ── Canvas helpers ────────────────────────────────────────────────────────────
function toCanvas(e) {
    const rect = canvas.value.getBoundingClientRect();
    return {
        x: Math.round((e.clientX - rect.left) * (680 / rect.width)),
        y: Math.round((e.clientY - rect.top)  * (520 / rect.height)),
    };
}

function centroid(vertices) {
    return {
        x: vertices.reduce((s, v) => s + v.x, 0) / vertices.length,
        y: vertices.reduce((s, v) => s + v.y, 0) / vertices.length,
    };
}

function pointInPolygon(x, y, vertices) {
    let inside = false;
    for (let i = 0, j = vertices.length - 1; i < vertices.length; j = i++) {
        const xi = vertices[i].x, yi = vertices[i].y;
        const xj = vertices[j].x, yj = vertices[j].y;
        if (((yi > y) !== (yj > y)) && (x < (xj - xi) * (y - yi) / (yj - yi) + xi)) inside = !inside;
    }
    return inside;
}

function hitPolygon(x, y) {
    // Reverse so topmost (last drawn) is hit first
    for (let i = polygons.value.length - 1; i >= 0; i--) {
        if (pointInPolygon(x, y, polygons.value[i].vertices)) return i;
    }
    return null;
}

function connectionExists(a, b) {
    return connections.value.some(c =>
        (c.a === a && c.b === b) || (c.a === b && c.b === a)
    );
}

// ── Geometry ──────────────────────────────────────────────────────────────────

/** SAT overlap check between two convex-ish polygons (good enough for editor use) */
function polygonsOverlap(vertsA, vertsB) {
    for (const verts of [vertsA, vertsB]) {
        for (let i = 0; i < verts.length; i++) {
            const j   = (i + 1) % verts.length;
            const nx  = -(verts[j].y - verts[i].y);
            const ny  =   verts[j].x - verts[i].x;
            const pA  = vertsA.map(v => v.x * nx + v.y * ny);
            const pB  = vertsB.map(v => v.x * nx + v.y * ny);
            if (Math.max(...pA) < Math.min(...pB) || Math.max(...pB) < Math.min(...pA)) return false;
        }
    }
    return true;
}

/** Does the proposed polygon overlap any existing one (excluding `excludeIdx`)? */
function wouldOverlap(verts, excludeIdx = -1) {
    for (let i = 0; i < polygons.value.length; i++) {
        if (i === excludeIdx) continue;
        if (polygonsOverlap(verts, polygons.value[i].vertices)) return true;
    }
    return false;
}

/** Point-to-segment distance */
function pointToSegmentDist(px, py, ax, ay, bx, by) {
    const dx = bx - ax, dy = by - ay;
    const lenSq = dx * dx + dy * dy;
    if (lenSq === 0) return Math.hypot(px - ax, py - ay);
    const t = Math.max(0, Math.min(1, ((px - ax) * dx + (py - ay) * dy) / lenSq));
    return Math.hypot(px - (ax + t * dx), py - (ay + t * dy));
}

/** Returns index of connection whose centroid-line is within `thresh` px of (x,y), or null */
function hitConnection(x, y, thresh = 8) {
    for (let i = 0; i < connections.value.length; i++) {
        const conn = connections.value[i];
        const a = polygons.value[conn.a];
        const b = polygons.value[conn.b];
        if (!a || !b) continue;
        const ca = centroid(a.vertices);
        const cb = centroid(b.vertices);
        if (pointToSegmentDist(x, y, ca.x, ca.y, cb.x, cb.y) <= thresh) return i;
    }
    return null;
}

// ── Mouse events ──────────────────────────────────────────────────────────────
const mousePos = ref(null);

function onMouseMove(e) {
    mousePos.value = toCanvas(e);

    if ((mode.value === 'select' || mode.value === 'draw') && dragging.value && selectedIdx.value !== null) {
        const { x, y } = mousePos.value;
        const dx = x - dragStart.value.x;
        const dy = y - dragStart.value.y;
        const proposed = dragOrigin.value.map(v => ({ x: v.x + dx, y: v.y + dy }));
        overlapWarn.value = wouldOverlap(proposed, selectedIdx.value);
        polygons.value[selectedIdx.value].vertices = proposed;
    }

    draw();
}

function onMouseLeave() {
    mousePos.value = null;
    draw();
}

function onMouseDown(e) {
    if (mode.value !== 'select' && !(mode.value === 'draw' && currentVertices.value.length === 0)) return;
    const { x, y } = toCanvas(e);
    const hit = hitPolygon(x, y);
    if (hit === null) { selectedIdx.value = null; draw(); return; }
    selectedIdx.value = hit;
    dragging.value    = true;
    dragStart.value   = { x, y };
    dragOrigin.value  = polygons.value[hit].vertices.map(v => ({ ...v }));
    draw();
}

function onMouseUp() {
    if (dragging.value) {
        // Revert if the final position overlaps another polygon
        if (overlapWarn.value && selectedIdx.value !== null) {
            polygons.value[selectedIdx.value].vertices = dragOrigin.value.map(v => ({ ...v }));
        }
        dragging.value    = false;
        overlapWarn.value = false;
    }
}

function onCanvasClick(e) {
    // Ignore click if we were dragging (mouseup already handled it)
    if (dragging.value) return;

    const { x, y } = toCanvas(e);

    if (mode.value === 'select') {
        const hit = hitPolygon(x, y);
        selectedIdx.value = hit;
        draw();
        return;
    }

    if (mode.value === 'draw') {
        // Snap to first vertex to close polygon
        if (currentVertices.value.length >= 3) {
            const first = currentVertices.value[0];
            if (Math.hypot(x - first.x, y - first.y) <= SNAP_RADIUS) {
                closePolygon();
                return;
            }
        }
        currentVertices.value.push({ x, y });
        draw();
        return;
    }

    if (mode.value === 'connect') {
        const hit = hitPolygon(x, y);
        if (hit === null) { connectA.value = null; draw(); return; }
        if (connectA.value === null) { connectA.value = hit; draw(); return; }
        if (connectA.value === hit) { connectA.value = null; draw(); return; }
        // Toggle connection
        if (connectionExists(connectA.value, hit)) {
            connections.value = connections.value.filter(c =>
                !((c.a === connectA.value && c.b === hit) || (c.a === hit && c.b === connectA.value))
            );
        } else {
            connections.value.push({ a: connectA.value, b: hit });
        }
        connectA.value = null;
        draw();
        return;
    }

    if (mode.value === 'disconnect') {
        const hit = hitConnection(x, y);
        if (hit !== null) {
            connections.value.splice(hit, 1);
            draw();
        }
        return;
    }

    if (mode.value === 'delete') {
        const hit = hitPolygon(x, y);
        if (hit === null) return;
        // Remove connections referencing this polygon, adjust indices
        connections.value = connections.value
            .filter(c => c.a !== hit && c.b !== hit)
            .map(c => ({
                a: c.a > hit ? c.a - 1 : c.a,
                b: c.b > hit ? c.b - 1 : c.b,
            }));
        polygons.value.splice(hit, 1);
        if (connectA.value === hit) connectA.value = null;
        draw();
    }
}

function onCanvasDblClick(e) {
    if (mode.value !== 'draw') return;
    if (currentVertices.value.length >= 3) closePolygon();
}

function closePolygon() {
    const verts = [...currentVertices.value];
    if (wouldOverlap(verts)) {
        // Flash a warning but don't close
        overlapWarn.value = true;
        setTimeout(() => { overlapWarn.value = false; draw(); }, 800);
        draw();
        return;
    }
    const newIdx = polygons.value.length;
    polygons.value.push({
        id: newIdx,
        name: `Region ${newIdx + 1}`,
        continent: continents.value[0]?.id ?? 'c1',
        vertices: verts,
    });
    currentVertices.value = [];
    // Auto-switch to select and highlight the new polygon
    mode.value        = 'select';
    selectedIdx.value = newIdx;
    draw();
}

// ── Draw ──────────────────────────────────────────────────────────────────────
function draw() {
    const ctx = canvas.value?.getContext('2d');
    if (!ctx) return;

    ctx.clearRect(0, 0, 680, 520);
    ctx.fillStyle = '#111827';
    ctx.fillRect(0, 0, 680, 520);

    // Draw connections
    for (let i = 0; i < connections.value.length; i++) {
        const conn = connections.value[i];
        const a = polygons.value[conn.a];
        const b = polygons.value[conn.b];
        if (!a || !b) continue;
        const ca = centroid(a.vertices);
        const cb = centroid(b.vertices);

        // In disconnect mode, highlight hovered connection
        const isHovered = mode.value === 'disconnect'
            && mousePos.value
            && hitConnection(mousePos.value.x, mousePos.value.y) === i;

        ctx.strokeStyle = isHovered ? '#ef4444' : 'rgba(255,255,255,0.2)';
        ctx.lineWidth   = isHovered ? 3 : 1.5;
        ctx.setLineDash(isHovered ? [] : [4, 4]);
        ctx.beginPath();
        ctx.moveTo(ca.x, ca.y);
        ctx.lineTo(cb.x, cb.y);
        ctx.stroke();
    }
    ctx.setLineDash([]);
    ctx.lineWidth = 1;

    // Draw completed polygons
    for (let i = 0; i < polygons.value.length; i++) {
        const poly = polygons.value[i];
        const cont = continents.value.find(c => c.id === poly.continent);
        const isSelected = i === connectA.value || i === selectedIdx.value;

        const isOverlapping = overlapWarn.value && i === selectedIdx.value;

        ctx.shadowColor = isSelected ? '#fff' : 'transparent';
        ctx.shadowBlur  = isSelected ? 16 : 0;

        // Fill
        ctx.fillStyle = isOverlapping
            ? 'rgba(239,68,68,0.45)'
            : cont ? hexToRgba(cont.color, 0.45) : 'rgba(55,65,81,0.5)';
        ctx.beginPath();
        poly.vertices.forEach((v, j) => j === 0 ? ctx.moveTo(v.x, v.y) : ctx.lineTo(v.x, v.y));
        ctx.closePath();
        ctx.fill();
        ctx.shadowBlur = 0;

        // Border
        ctx.strokeStyle = isOverlapping ? '#ef4444' : isSelected ? '#fff' : (cont?.color ?? 'rgba(255,255,255,0.4)');
        ctx.lineWidth   = isSelected || isOverlapping ? 2.5 : 1.5;
        ctx.stroke();
        ctx.lineWidth = 1;

        // Vertex dots
        ctx.fillStyle = 'rgba(255,255,255,0.5)';
        for (const v of poly.vertices) {
            ctx.beginPath();
            ctx.arc(v.x, v.y, 3, 0, Math.PI * 2);
            ctx.fill();
        }

        // Label
        const c = centroid(poly.vertices);
        ctx.font         = 'bold 11px sans-serif';
        ctx.fillStyle    = '#fff';
        ctx.textAlign    = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(poly.name.slice(0, 10), c.x, c.y);
    }

    // Draw in-progress polygon
    if (currentVertices.value.length > 0) {
        const verts = currentVertices.value;
        const previewVerts = mousePos.value ? [...verts, mousePos.value] : verts;
        const wouldHit = overlapWarn.value || (verts.length >= 2 && wouldOverlap(previewVerts));

        // Snap indicator on first vertex
        if (verts.length >= 3) {
            ctx.strokeStyle = 'rgba(255,255,255,0.5)';
            ctx.lineWidth   = 1;
            ctx.beginPath();
            ctx.arc(verts[0].x, verts[0].y, SNAP_RADIUS, 0, Math.PI * 2);
            ctx.stroke();
        }

        ctx.strokeStyle = wouldHit ? '#ef4444' : '#fbbf24';
        ctx.lineWidth   = 1.5;
        ctx.beginPath();
        ctx.moveTo(verts[0].x, verts[0].y);
        for (let i = 1; i < verts.length; i++) ctx.lineTo(verts[i].x, verts[i].y);

        // Line to mouse cursor
        if (mousePos.value) ctx.lineTo(mousePos.value.x, mousePos.value.y);
        ctx.stroke();

        // Vertex dots
        ctx.fillStyle = wouldHit ? '#ef4444' : '#fbbf24';
        for (const v of verts) {
            ctx.beginPath();
            ctx.arc(v.x, v.y, 4, 0, Math.PI * 2);
            ctx.fill();
        }
    }
}

function hexToRgba(hex, alpha) {
    const r = parseInt(hex.slice(1,3),16);
    const g = parseInt(hex.slice(3,5),16);
    const b = parseInt(hex.slice(5,7),16);
    return `rgba(${r},${g},${b},${alpha})`;
}

onMounted(() => {
    draw();
    window.addEventListener('keydown', onKeyDown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeyDown);
});

// ── Continents ────────────────────────────────────────────────────────────────
function addContinent() {
    const id = `c${continents.value.length + 1}`;
    continents.value.push({
        id,
        name: `Continent ${continents.value.length + 1}`,
        bonus: 2,
        color: CONTINENT_COLORS[continents.value.length % CONTINENT_COLORS.length],
    });
}

// ── Save ──────────────────────────────────────────────────────────────────────
async function saveMap() {
    if (!mapName.value || polygons.value.length < 3) {
        saveMsg.value = 'Need a name and at least 3 polygons.';
        return;
    }
    saving.value = true;
    try {
        const res = await window.axios.post(route('kirkconnel.map-editor.store'), {
            name:        mapName.value,
            description: mapDescription.value,
            continents:  continents.value,
            polygons:    polygons.value.map(p => ({
                name:      p.name,
                continent: p.continent,
                vertices:  p.vertices,
            })),
            connections: connections.value,
        });
        saveMsg.value = res.data.message;
    } catch (e) {
        saveMsg.value = 'Save failed: ' + (e.response?.data?.message ?? 'Unknown error');
    } finally {
        saving.value = false;
    }
}

function setMode(m) {
    mode.value            = m;
    connectA.value        = null;
    selectedIdx.value     = null;
    dragging.value        = false;
    currentVertices.value = [];
    draw();
}
</script>

<template>
    <AppLayout title="Map Editor">
        <Head title="Map Editor" />
        <div class="max-w-screen-xl mx-auto px-8 md:px-16 py-8" style="color:#f9fafb">
            <h1 class="text-3xl font-bold uppercase mb-2">Map Editor</h1>
            <p class="text-white/50 text-sm mb-6">
                Draw: click to place vertices, click first vertex or double-click to close. Escape cancels.
                Select: click to select, drag to move (snaps back if overlapping).
                Connect: click two polygons to link. Disconnect: click a connection line to remove it.
            </p>

            <div class="flex gap-6 items-start">
                <!-- Canvas -->
                <div>
                    <canvas
                        ref="canvas"
                        width="680"
                        height="520"
                        class="block rounded-lg"
                        :class="mode === 'draw' ? 'cursor-crosshair' : mode === 'delete' ? 'cursor-not-allowed' : mode === 'select' ? (dragging ? 'cursor-grabbing' : 'cursor-grab') : 'cursor-pointer'"
                        style="background:#111827; width:680px; height:520px;"
                        @click="onCanvasClick"
                        @dblclick="onCanvasDblClick"
                        @mousemove="onMouseMove"
                        @mouseleave="onMouseLeave"
                        @mousedown="onMouseDown"
                        @mouseup="onMouseUp"
                    />
                    <p v-if="currentVertices.length > 0" class="text-xs text-yellow-400 mt-1">
                        {{ currentVertices.length }} vertices placed — click first vertex or double-click to close polygon
                    </p>
                </div>

                <!-- Controls -->
                <div class="w-64 flex flex-col gap-4 shrink-0">

                    <!-- Map info -->
                    <div class="bg-secondary rounded-lg p-4">
                        <p class="text-xs uppercase font-bold text-white/50 mb-3">Map Details</p>
                        <input v-model="mapName" placeholder="Map name" class="w-full bg-white/5 border border-white/10 rounded px-3 py-1.5 text-sm mb-2" style="color:#111827; background-color:#e5e7eb" />
                        <textarea v-model="mapDescription" placeholder="Description (optional)" rows="2" class="w-full bg-white/5 border border-white/10 rounded px-3 py-1.5 text-sm resize-none" style="color:#111827; background-color:#e5e7eb" />
                    </div>

                    <!-- Tool mode -->
                    <div class="bg-secondary rounded-lg p-4">
                        <p class="text-xs uppercase font-bold text-white/50 mb-2">Tool</p>
                        <div class="flex flex-col gap-1">
                            <button
                                v-for="(label, m) in { draw: '✏ Draw Polygon', select: '⬡ Select / Move', connect: '↔ Connect Polygons', disconnect: '✂ Disconnect', delete: '✕ Delete Polygon' }"
                                :key="m"
                                @click="setMode(m)"
                                class="py-1.5 px-3 rounded text-sm text-left transition"
                                :class="mode === m ? 'bg-accent text-white' : 'bg-white/10 text-white/60 hover:bg-white/20'"
                            >{{ label }}</button>
                        </div>
                    </div>

                    <!-- Polygon list -->
                    <div class="bg-secondary rounded-lg p-4 max-h-48 overflow-y-auto">
                        <p class="text-xs uppercase font-bold text-white/50 mb-2">Polygons ({{ polygons.length }})</p>
                        <div v-if="!polygons.length" class="text-xs text-white/30">None yet — draw some!</div>
                        <div v-for="(poly, i) in polygons" :key="i" class="mb-2">
                            <input
                                v-model="poly.name"
                                @input="draw()"
                                class="w-full bg-white/5 border border-white/10 rounded px-2 py-1 text-xs mb-1"
                                style="color:#111827; background-color:#e5e7eb"
                            />
                            <select
                                v-model="poly.continent"
                                @change="draw()"
                                class="w-full bg-white/5 border border-white/10 rounded px-2 py-1 text-xs"
                                style="color:#111827; background-color:#e5e7eb"
                            >
                                <option v-for="c in continents" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- Continents -->
                    <div class="bg-secondary rounded-lg p-4">
                        <p class="text-xs uppercase font-bold text-white/50 mb-2">Continents</p>
                        <div v-for="c in continents" :key="c.id" class="flex items-center gap-2 mb-2">
                            <span class="w-3 h-3 rounded-sm shrink-0" :style="{ backgroundColor: c.color }"></span>
                            <input v-model="c.name" @input="draw()" class="flex-1 bg-white/5 border border-white/10 rounded px-2 py-1 text-xs" style="color:#111827; background-color:#e5e7eb" />
                            <input v-model.number="c.bonus" type="number" min="1" max="10" class="w-10 bg-white/5 border border-white/10 rounded px-1 py-1 text-xs text-center" style="color:#111827; background-color:#e5e7eb" />
                        </div>
                        <button @click="addContinent" class="text-xs text-accent hover:underline">+ Add Continent</button>
                    </div>

                    <!-- Stats -->
                    <div class="bg-secondary rounded-lg p-4 text-xs text-white/50">
                        <p>Polygons: {{ polygons.length }}</p>
                        <p>Connections: {{ connections.length }}</p>
                    </div>

                    <!-- Save -->
                    <button @click="saveMap" :disabled="saving" class="w-full py-2 rounded bg-accent text-white font-bold hover:opacity-80 disabled:opacity-40">
                        {{ saving ? 'Saving...' : 'Submit Map' }}
                    </button>
                    <p v-if="saveMsg" class="text-xs text-center" :style="saveMsg.includes('failed') ? 'color:#f87171' : 'color:#4ade80'">{{ saveMsg }}</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
