import {
    TILE_SIZE, MAP_W, MAP_H, HUD_H, SIDEBAR_W, CANVAS_W,
    TOWER_TYPES, UPGRADE_LEVELS,
} from './constants.js';

// ── Colours ──────────────────────────────────────────────────────────────────
const C = {
    land:       '#4a7c59',
    path:       '#c2a96e',
    gridLine:   'rgba(0,0,0,0.07)',
    hudBg:      '#111827',
    sidebarBg:  '#1f2937',
    text:       '#f9fafb',
    textMuted:  '#9ca3af',
    btnBg:      '#374151',
    btnHover:   '#4b5563',
    gold:       '#fbbf24',
    lives:      '#f87171',
    accent:     '#e94f37',
    waveActive: '#fcd34d',
    barBg:      '#374151',
    barHp:      '#22c55e',
    barHpMid:   '#f59e0b',
    barHpLow:   '#ef4444',
    upgradePip: '#fbbf24',
    upgradeBg:  '#374151',
    sellBtn:    '#7f1d1d',
    upgradeBtn: '#78350f',
};

// ── Helpers ───────────────────────────────────────────────────────────────────
function roundRect(ctx, x, y, w, h, r, fill, stroke) {
    ctx.beginPath();
    ctx.roundRect(x, y, w, h, r);
    if (fill)   { ctx.fillStyle = fill;   ctx.fill();   }
    if (stroke) { ctx.strokeStyle = stroke; ctx.stroke(); }
}

function text(ctx, str, x, y, size, color, align = 'left', bold = false) {
    ctx.font = `${bold ? 'bold ' : ''}${size}px sans-serif`;
    ctx.fillStyle = color;
    ctx.textAlign = align;
    ctx.textBaseline = 'middle';
    ctx.fillText(str, x, y);
}

function button(ctx, label, x, y, w, h, bg, textColor, hovered) {
    roundRect(ctx, x, y, w, h, 6, hovered ? C.btnHover : bg);
    text(ctx, label, x + w / 2, y + h / 2, 12, textColor, 'center', true);
    return { x, y, w, h, label };
}

// ── HUD ───────────────────────────────────────────────────────────────────────
function drawHUD(ctx, engine, buttons, hovered) {
    roundRect(ctx, 0, 0, CANVAS_W, HUD_H, 0, C.hudBg);

    text(ctx, `💰 ${engine.gold}g`, 16, HUD_H / 2, 14, C.gold, 'left', true);
    text(ctx, `❤️  ${engine.lives}`, 110, HUD_H / 2, 14, C.lives, 'left', true);
    text(ctx, `Wave ${engine.wave}`, 200, HUD_H / 2, 14, C.text, 'left', true);
    text(ctx, `Score ${engine.score}`, 290, HUD_H / 2, 14, C.textMuted, 'left');

    if (engine.gameOver) {
        text(ctx, 'GAME OVER', MAP_W / 2, HUD_H / 2, 18, C.accent, 'center', true);
        buttons.push(button(ctx, 'Restart', MAP_W / 2 + 80, 8, 80, 32, C.accent, C.text, hovered === 'Restart'));
    } else if (engine.waveActive) {
        text(ctx, 'Wave in progress...', MAP_W / 2, HUD_H / 2, 13, C.waveActive, 'center');
    } else if (engine.countdown !== null) {
        const secs = Math.ceil(engine.countdown / 1000);
        text(ctx, `Next wave in ${secs}s`, MAP_W / 2 - 60, HUD_H / 2, 13, C.waveActive, 'center');
        buttons.push(button(ctx, 'Send Now', MAP_W / 2 + 30, 8, 90, 32, C.accent, C.text, hovered === 'Send Now'));
    } else {
        const lbl = engine.wave === 0 ? 'Start Game' : 'Next Wave';
        buttons.push(button(ctx, lbl, MAP_W / 2 - 50, 8, 100, 32, C.accent, C.text, hovered === lbl));
    }
}

// ── Map ───────────────────────────────────────────────────────────────────────
function drawMap(ctx, grid, offsetY) {
    for (let row = 0; row < grid.length; row++) {
        for (let col = 0; col < grid[row].length; col++) {
            const x = col * TILE_SIZE, y = offsetY + row * TILE_SIZE;
            ctx.fillStyle = grid[row][col] === 1 ? C.path : C.land;
            ctx.fillRect(x, y, TILE_SIZE, TILE_SIZE);
            ctx.strokeStyle = C.gridLine;
            ctx.strokeRect(x, y, TILE_SIZE, TILE_SIZE);
        }
    }
}

// ── Hover tile ────────────────────────────────────────────────────────────────
function drawHoverTile(ctx, col, row, canPlace, offsetY) {
    ctx.fillStyle = canPlace ? 'rgba(255,255,255,0.15)' : 'rgba(239,68,68,0.25)';
    ctx.fillRect(col * TILE_SIZE, offsetY + row * TILE_SIZE, TILE_SIZE, TILE_SIZE);
}

// ── Tower ─────────────────────────────────────────────────────────────────────
function drawTower(ctx, tower, selected, offsetY) {
    const { x, y: ty, type, upgradeLevel } = tower;
    const cy = ty + offsetY;
    const def = TOWER_TYPES[type];
    const half = TILE_SIZE / 2 - 4 + upgradeLevel;

    if (selected) {
        ctx.strokeStyle = '#fbbf24';
        ctx.lineWidth = 2;
        ctx.strokeRect(tower.col * TILE_SIZE + 1, offsetY + tower.row * TILE_SIZE + 1, TILE_SIZE - 2, TILE_SIZE - 2);
        ctx.lineWidth = 1;
        // range ring
        ctx.strokeStyle = 'rgba(255,255,255,0.2)';
        ctx.setLineDash([4, 4]);
        ctx.beginPath(); ctx.arc(x, cy, tower.range, 0, Math.PI * 2); ctx.stroke();
        ctx.setLineDash([]);
    }

    if (upgradeLevel > 0) { ctx.shadowColor = def.color; ctx.shadowBlur = 4 + upgradeLevel * 3; }
    ctx.fillStyle = def.color;
    ctx.fillRect(x - half, cy - half, half * 2, half * 2);
    ctx.shadowBlur = 0;

    // upgrade pips
    if (upgradeLevel > 0) {
        const pips = [[-half+5,-half+5],[half-5,-half+5],[-half+5,half-5],[half-5,half-5],[0,-half+5]];
        ctx.fillStyle = '#fff';
        for (let i = 0; i < upgradeLevel; i++) {
            ctx.beginPath(); ctx.arc(x + pips[i][0], cy + pips[i][1], 3, 0, Math.PI * 2); ctx.fill();
        }
    }

    // melee swing
    if (type === 'black' && tower.swinging) {
        ctx.strokeStyle = def.circleColor; ctx.lineWidth = 3;
        ctx.beginPath(); ctx.arc(x, cy, tower.range * 0.6, tower.swingAngle - 0.6, tower.swingAngle + 0.6); ctx.stroke();
        ctx.lineWidth = 1;
        tower.swingAngle += 0.25 * tower.swingDir;
        if (Math.abs(tower.swingAngle) > 1.2) tower.swingDir *= -1;
        if (tower.swingAngle < -1.2) tower.swinging = false;
    }

    // centre circle
    ctx.fillStyle = def.circleColor;
    ctx.beginPath(); ctx.arc(x, cy, Math.max(5, half * 0.45), 0, Math.PI * 2); ctx.fill();
}

// ── Monster ───────────────────────────────────────────────────────────────────
const MONSTER_PALETTE = ['#9ca3af','#6b7280','#d1d5db','#a3a3a3','#71717a'];

function drawMonster(ctx, m, offsetY) {
    const cy = m.y + offsetY;
    ctx.fillStyle = MONSTER_PALETTE[(m.wave - 1) % MONSTER_PALETTE.length];
    ctx.beginPath(); ctx.arc(m.x, cy, 10, 0, Math.PI * 2); ctx.fill();
    ctx.strokeStyle = '#374151'; ctx.lineWidth = 1.5;
    ctx.stroke(); ctx.lineWidth = 1;

    const ratio = m.hp / m.maxHp;
    const bw = 20, bh = 3;
    ctx.fillStyle = C.barBg;
    ctx.fillRect(m.x - bw / 2, cy - 16, bw, bh);
    ctx.fillStyle = ratio > 0.5 ? C.barHp : ratio > 0.25 ? C.barHpMid : C.barHpLow;
    ctx.fillRect(m.x - bw / 2, cy - 16, bw * ratio, bh);
}

// ── Bullet ────────────────────────────────────────────────────────────────────
function drawBullet(ctx, b, offsetY) {
    ctx.fillStyle = b.color;
    ctx.shadowColor = b.color; ctx.shadowBlur = 6;
    ctx.beginPath(); ctx.arc(b.x, b.y + offsetY, 4, 0, Math.PI * 2); ctx.fill();
    ctx.shadowBlur = 0;
}

// ── Sidebar ───────────────────────────────────────────────────────────────────
function drawSidebar(ctx, engine, buttons, hovered) {
    const sx = MAP_W, sy = HUD_H;
    const sw = SIDEBAR_W, sh = MAP_H;
    roundRect(ctx, sx, sy, sw, sh, 0, C.sidebarBg);

    let cy = sy + 16;
    const cx = sx + 12;
    const bw = sw - 24;

    // ── Tower picker ──
    text(ctx, 'PLACE TOWER', cx + bw / 2, cy, 11, C.textMuted, 'center', true);
    cy += 18;

    for (const [type, def] of Object.entries(TOWER_TYPES)) {
        const canAfford = engine.gold >= def.cost;
        const isSelected = engine.pendingType === type;
        roundRect(ctx, cx, cy, bw, 34, 6, isSelected ? def.color : C.btnBg,
            isSelected ? '#fff' : (hovered === `place_${type}` ? (type === 'black' ? '#9ca3af' : def.color) : null));
        // colour swatch
        ctx.fillStyle = def.color;
        ctx.beginPath(); ctx.arc(cx + 16, cy + 17, 8, 0, Math.PI * 2); ctx.fill();
        ctx.fillStyle = def.circleColor;
        ctx.beginPath(); ctx.arc(cx + 16, cy + 17, 4, 0, Math.PI * 2); ctx.fill();
        text(ctx, `${def.name}`, cx + 32, cy + 11, 12, canAfford ? C.text : C.textMuted, 'left', true);
        text(ctx, `${def.cost}g`, cx + 32, cy + 24, 11, canAfford ? C.gold : C.textMuted, 'left');
        buttons.push({ x: cx, y: cy, w: bw, h: 34, label: `place_${type}` });
        cy += 40;
    }

    // ── Selected tower ──
    const sel = engine.selectedTower;
    if (sel) {
        cy += 8;
        ctx.strokeStyle = '#374151'; ctx.lineWidth = 1;
        ctx.beginPath(); ctx.moveTo(cx, cy); ctx.lineTo(cx + bw, cy); ctx.stroke();
        cy += 12;

        const def = TOWER_TYPES[sel.type];
        text(ctx, def.name.toUpperCase(), cx + bw / 2, cy, 12, def.color, 'center', true);
        cy += 18;
        text(ctx, `Level ${sel.upgradeLevel} / ${UPGRADE_LEVELS.length}`, cx + bw / 2, cy, 11, C.textMuted, 'center');
        cy += 16;

        // upgrade bar
        const pipW = (bw - 4) / UPGRADE_LEVELS.length;
        for (let i = 0; i < UPGRADE_LEVELS.length; i++) {
            roundRect(ctx, cx + i * (pipW + 1), cy, pipW, 6, 2,
                i < sel.upgradeLevel ? C.upgradePip : C.upgradeBg);
        }
        cy += 14;

        text(ctx, `DMG ${sel.damage}  RNG ${sel.range}  RATE ${sel.fireRate}ms`, cx + bw / 2, cy, 10, C.textMuted, 'center');
        cy += 18;

        // upgrade button
        const maxed = sel.upgradeLevel >= UPGRADE_LEVELS.length;
        const upgCost = sel.upgradeCost;
        const canUpg = !maxed && engine.gold >= upgCost;
        const upgLabel = maxed ? 'MAX LEVEL' : `Upgrade  ${upgCost}g`;
        roundRect(ctx, cx, cy, bw, 30, 6, canUpg ? C.upgradeBtn : C.btnBg);
        text(ctx, upgLabel, cx + bw / 2, cy + 15, 12, canUpg ? C.gold : C.textMuted, 'center', true);
        if (!maxed) buttons.push({ x: cx, y: cy, w: bw, h: 30, label: 'upgrade' });
        cy += 36;

        // sell button
        const base = TOWER_TYPES[sel.type].cost;
        let spent = 0;
        for (let i = 0; i < sel.upgradeLevel; i++) spent += UPGRADE_LEVELS[i].cost;
        const refund = Math.floor(base * 0.5 + spent * 0.3);
        roundRect(ctx, cx, cy, bw, 30, 6, C.sellBtn);
        text(ctx, `Sell  +${refund}g`, cx + bw / 2, cy + 15, 12, '#fca5a5', 'center', true);
        buttons.push({ x: cx, y: cy, w: bw, h: 30, label: 'sell' });
        cy += 36;
    }

    // ── Help ──
    cy = sy + sh - 90;
    text(ctx, 'HOW TO PLAY', cx + bw / 2, cy, 10, C.textMuted, 'center', true); cy += 14;
    for (const line of ['Pick a tower below','Click green tile to place','Click tower to select','Press Next Wave to start']) {
        text(ctx, line, cx + bw / 2, cy, 10, C.textMuted, 'center'); cy += 13;
    }
}

// ── Main render ───────────────────────────────────────────────────────────────
export function render(ctx, engine, hoverTile, hoveredBtn) {
    const buttons = [];
    const offsetY = HUD_H;

    ctx.clearRect(0, 0, CANVAS_W, ctx.canvas.height);

    drawMap(ctx, engine.grid, offsetY);

    if (hoverTile && engine.pendingType) {
        drawHoverTile(ctx, hoverTile.col, hoverTile.row, engine.canPlace(hoverTile.col, hoverTile.row), offsetY);
    }

    for (const tower of Object.values(engine.towers)) {
        drawTower(ctx, tower, engine.selectedKey === `${tower.col},${tower.row}`, offsetY);
    }

    for (const m of engine.monsters)  drawMonster(ctx, m, offsetY);
    for (const b of engine.bullets)   drawBullet(ctx, b, offsetY);

    drawHUD(ctx, engine, buttons, hoveredBtn);
    drawSidebar(ctx, engine, buttons, hoveredBtn);

    // store hit areas back on engine for click handling
    engine.uiButtons = buttons;
}
