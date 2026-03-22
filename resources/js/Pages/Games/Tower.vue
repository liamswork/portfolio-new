<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { GameEngine } from '@/game/tower-defence/engine.js';
import { render } from '@/game/tower-defence/renderer.js';
import { TILE_SIZE, HUD_H, CANVAS_W, CANVAS_H, TOWER_TYPES } from '@/game/tower-defence/constants.js';

const canvas   = ref(null);
const engine   = new GameEngine();
const hover    = ref(null);
const hoveredBtn = ref(null);
let   raf      = null;
let   dpr      = 1;

// ── Loop ─────────────────────────────────────────────────────────────────────
function loop(now) {
    engine.update(now);
    const ctx = canvas.value?.getContext('2d');
    if (ctx) {
        ctx.save();
        ctx.scale(dpr, dpr);
        render(ctx, engine, hover.value, hoveredBtn.value);
        ctx.restore();
    }
    raf = requestAnimationFrame(loop);
}

onMounted(() => {
    dpr = window.devicePixelRatio || 1;
    const el = canvas.value;
    el.width  = CANVAS_W * dpr;
    el.height = CANVAS_H * dpr;
    el.style.width  = `${CANVAS_W}px`;
    el.style.height = `${CANVAS_H}px`;
    raf = requestAnimationFrame(loop);
});
onUnmounted(() => cancelAnimationFrame(raf));

// ── Coordinate helpers ────────────────────────────────────────────────────────
function canvasPos(e) {
    const rect = canvas.value.getBoundingClientRect();
    return {
        x: (e.clientX - rect.left) * (CANVAS_W / rect.width),
        y: (e.clientY - rect.top)  * (CANVAS_H / rect.height),
    };
}

function toTile(pos) {
    const row = Math.floor((pos.y - HUD_H) / TILE_SIZE);
    const col = Math.floor(pos.x / TILE_SIZE);
    return { col, row };
}

function hitButton(pos) {
    return engine.uiButtons.find(b =>
        pos.x >= b.x && pos.x <= b.x + b.w &&
        pos.y >= b.y && pos.y <= b.y + b.h
    ) || null;
}

// ── Events ────────────────────────────────────────────────────────────────────
function onMouseMove(e) {
    const pos = canvasPos(e);
    const btn = hitButton(pos);
    hoveredBtn.value = btn ? btn.label : null;

    const { col, row } = toTile(pos);
    hover.value = (row >= 0 && col >= 0) ? { col, row } : null;
}

function onMouseLeave() {
    hover.value = null;
    hoveredBtn.value = null;
}

function onClick(e) {
    const pos  = canvasPos(e);
    const btn  = hitButton(pos);

    if (btn) {
        handleButton(btn.label);
        return;
    }

    // Map click
    const { col, row } = toTile(pos);
    if (row < 0 || col < 0) return;

    if (engine.pendingType) {
        engine.place(col, row, engine.pendingType);
        engine.pendingType = null;
        return;
    }

    const key = `${col},${row}`;
    if (engine.towers[key]) {
        engine.selectedKey = engine.selectedKey === key ? null : key;
    } else {
        engine.selectedKey = null;
    }
}

function handleButton(label) {
    if (label === 'Start Game' || label === 'Next Wave' || label === 'Send Now') { engine.startWave(); return; }
    if (label === 'Restart')  { engine.reset(); return; }
    if (label === 'upgrade')  { engine.upgrade(engine.selectedKey); return; }
    if (label === 'sell')     { engine.sell(engine.selectedKey); return; }
    if (label.startsWith('place_')) {
        const type = label.replace('place_', '');
        engine.pendingType = engine.pendingType === type ? null : type;
        engine.selectedKey = null;
    }
}
</script>

<template>
    <AppLayout title="Tower Defence">
        <Head title="Tower Defence" />
        <div class="max-w-screen-xl mx-auto px-8 md:px-16 py-8">
            <h1 class="text-3xl font-bold uppercase mb-4">Tower Defence</h1>
            <canvas
                ref="canvas"
                :width="CANVAS_W"
                :height="CANVAS_H"
                class="block rounded cursor-crosshair"
                style="max-width: 100%;"
                @mousemove="onMouseMove"
                @mouseleave="onMouseLeave"
                @click="onClick"
            />
        </div>
    </AppLayout>
</template>
