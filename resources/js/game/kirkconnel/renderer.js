// Renders the Kirkconnel game map onto a canvas using polygons
// All game state is passed in — no internal state

const FONT      = '11px sans-serif';
const FONT_BOLD = 'bold 12px sans-serif';

function hexToRgba(hex, alpha) {
    const r = parseInt(hex.slice(1,3),16);
    const g = parseInt(hex.slice(3,5),16);
    const b = parseInt(hex.slice(5,7),16);
    return `rgba(${r},${g},${b},${alpha})`;
}

/** Build a Path2D from a polygon's vertices */
function polygonPath(vertices) {
    const path = new Path2D();
    if (!vertices.length) return path;
    path.moveTo(vertices[0].x, vertices[0].y);
    for (let i = 1; i < vertices.length; i++) path.lineTo(vertices[i].x, vertices[i].y);
    path.closePath();
    return path;
}

/** Centroid of a polygon */
function centroid(vertices) {
    const x = vertices.reduce((s, v) => s + v.x, 0) / vertices.length;
    const y = vertices.reduce((s, v) => s + v.y, 0) / vertices.length;
    return { x, y };
}

/** Draw connection lines between polygon centroids */
function drawConnections(ctx, polygons) {
    ctx.strokeStyle = 'rgba(255,255,255,0.15)';
    ctx.lineWidth   = 1.5;
    ctx.setLineDash([4, 4]);
    const drawn = new Set();
    for (const p of polygons) {
        for (const cid of (p.connections ?? [])) {
            const key = [p.id, cid].sort().join('-');
            if (drawn.has(key)) continue;
            drawn.add(key);
            const other = polygons.find(x => x.id === cid);
            if (!other) continue;
            const a = p.centroid;
            const b = other.centroid;
            ctx.beginPath();
            ctx.moveTo(a.x, a.y);
            ctx.lineTo(b.x, b.y);
            ctx.stroke();
        }
    }
    ctx.setLineDash([]);
    ctx.lineWidth = 1;
}

/** Draw continent background tint behind each polygon */
function drawContinentBg(ctx, polygons, continents) {
    for (const continent of continents) {
        const members = polygons.filter(p => p.continent === continent.id);
        ctx.fillStyle = hexToRgba(continent.color, 0.18);
        for (const p of members) {
            ctx.fill(polygonPath(p.vertices));
        }
    }
}

/** Draw a single polygon territory */
function drawPolygon(ctx, polygon, ownerColor, armies, isSelected, isHighlighted, isAttackTarget) {
    const path = polygonPath(polygon.vertices);
    const c    = polygon.centroid;

    // Shadow / glow
    if (isSelected) {
        ctx.shadowColor = '#fff';
        ctx.shadowBlur  = 20;
    } else if (isAttackTarget) {
        ctx.shadowColor = '#ef4444';
        ctx.shadowBlur  = 16;
    } else if (isHighlighted) {
        ctx.shadowColor = ownerColor || '#fff';
        ctx.shadowBlur  = 12;
    }

    // Fill
    ctx.fillStyle = ownerColor
        ? hexToRgba(ownerColor, isSelected ? 0.85 : 0.55)
        : 'rgba(55,65,81,0.6)';
    ctx.fill(path);
    ctx.shadowBlur = 0;

    // Border
    ctx.strokeStyle = isSelected
        ? '#fff'
        : isAttackTarget
            ? '#ef4444'
            : ownerColor
                ? hexToRgba(ownerColor, 0.9)
                : 'rgba(255,255,255,0.25)';
    ctx.lineWidth = isSelected || isAttackTarget ? 2.5 : 1.5;
    ctx.stroke(path);
    ctx.lineWidth = 1;

    // Army count at centroid
    ctx.font         = FONT_BOLD;
    ctx.fillStyle    = '#fff';
    ctx.textAlign    = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(armies ?? 0, c.x, c.y);

    // Name below centroid
    ctx.font      = FONT;
    ctx.fillStyle = 'rgba(255,255,255,0.7)';
    ctx.fillText(polygon.name, c.x, c.y + 14);
}

/** Main render function */
export function renderMap(ctx, mapData, gameState, myUserId, selectedId, highlightIds, attackTargetIds, playerColors, animations = []) {
    const { polygons: mapPolygons, continents } = mapData;
    const statePolygons = gameState?.polygons || [];

    // Merge layout with game state
    const polygons = mapPolygons.map(p => {
        const state = statePolygons.find(s => s.id === p.id) || {};
        return { ...p, owner: state.owner || null, armies: state.armies || 0 };
    });

    ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
    ctx.fillStyle = '#111827';
    ctx.fillRect(0, 0, ctx.canvas.width, ctx.canvas.height);

    drawContinentBg(ctx, polygons, continents);
    drawConnections(ctx, polygons);

    for (const p of polygons) {
        const color = p.owner ? playerColors[p.owner] : null;
        drawPolygon(
            ctx, p, color, p.armies,
            selectedId === p.id,
            highlightIds?.includes(p.id),
            attackTargetIds?.includes(p.id),
        );
    }

    drawAnimations(ctx, animations);
}

/**
 * Hit test — returns polygon id or null.
 * Uses point-in-polygon (ray casting) against the actual polygon vertices.
 */
export function hitTest(x, y, mapPolygons) {
    for (const p of mapPolygons) {
        if (pointInPolygon(x, y, p.vertices)) return p.id;
    }
    return null;
}

function pointInPolygon(x, y, vertices) {
    let inside = false;
    for (let i = 0, j = vertices.length - 1; i < vertices.length; j = i++) {
        const xi = vertices[i].x, yi = vertices[i].y;
        const xj = vertices[j].x, yj = vertices[j].y;
        const intersect = ((yi > y) !== (yj > y)) && (x < (xj - xi) * (y - yi) / (yj - yi) + xi);
        if (intersect) inside = !inside;
    }
    return inside;
}

// ── Attack animation ──────────────────────────────────────────────────────────
export function tickAnimations(animations) {
    for (const a of animations) a.progress = Math.min(1, a.progress + 0.04);
    return animations.filter(a => a.progress < 1);
}

function drawAnimations(ctx, animations) {
    for (const a of animations) {
        const t = a.progress;
        const x = a.fromX + (a.toX - a.fromX) * t;
        const y = a.fromY + (a.toY - a.fromY) * t;

        ctx.strokeStyle = a.success ? 'rgba(239,68,68,0.5)' : 'rgba(156,163,175,0.4)';
        ctx.lineWidth   = 2;
        ctx.setLineDash([4, 4]);
        ctx.beginPath();
        ctx.moveTo(a.fromX, a.fromY);
        ctx.lineTo(x, y);
        ctx.stroke();
        ctx.setLineDash([]);
        ctx.lineWidth = 1;

        ctx.fillStyle   = a.success ? '#ef4444' : '#9ca3af';
        ctx.shadowColor = a.success ? '#ef4444' : '#9ca3af';
        ctx.shadowBlur  = 10;
        ctx.beginPath();
        ctx.arc(x, y, 6, 0, Math.PI * 2);
        ctx.fill();
        ctx.shadowBlur = 0;

        if (t > 0.85) {
            const alpha = (1 - t) / 0.15;
            ctx.fillStyle = `rgba(${a.success ? '239,68,68' : '156,163,175'},${alpha * 0.3})`;
            ctx.beginPath();
            ctx.arc(a.toX, a.toY, 30 * (1 + (1 - alpha) * 0.5), 0, Math.PI * 2);
            ctx.fill();
        }
    }
}
