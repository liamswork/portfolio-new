import {
    TILE_SIZE, MAP_GRID, PATH_WAYPOINTS,
    TOWER_TYPES, UPGRADE_LEVELS,
    MONSTERS_PER_WAVE, MONSTER_SPAWN_MS,
    MONSTER_BASE_HP, MONSTER_HP_SCALE, MONSTER_SPEED,
    STARTING_GOLD, STARTING_LIVES, WAVE_COUNTDOWN_MS,
} from './constants.js';

function tileCenter(col, row) {
    return { x: col * TILE_SIZE + TILE_SIZE / 2, y: row * TILE_SIZE + TILE_SIZE / 2 };
}

function dist(a, b) { return Math.hypot(a.x - b.x, a.y - b.y); }

function buildPath() {
    return PATH_WAYPOINTS.map(([c, r]) => tileCenter(c, r));
}

// ── Tower ────────────────────────────────────────────────────────────────────
export class Tower {
    constructor(col, row, type) {
        this.col = col; this.row = row; this.type = type;
        this.upgradeLevel = 0;
        this.lastFired = 0;
        this.swinging = false; this.swingAngle = 0; this.swingDir = 1;
        const b = TOWER_TYPES[type];
        this.range = b.range; this.fireRate = b.fireRate; this.damage = b.damage;
        const c = tileCenter(col, row);
        this.x = c.x; this.y = c.y;
    }
    get upgradeCost() {
        return this.upgradeLevel < UPGRADE_LEVELS.length ? UPGRADE_LEVELS[this.upgradeLevel].cost : null;
    }
    upgrade() {
        if (this.upgradeLevel >= UPGRADE_LEVELS.length) return false;
        const l = UPGRADE_LEVELS[this.upgradeLevel];
        this.range    += l.rangeBonus;
        this.fireRate  = Math.floor(this.fireRate * l.fireRateMult);
        this.damage   += l.damageBonus;
        this.upgradeLevel++;
        return true;
    }
}

// ── Monster ──────────────────────────────────────────────────────────────────
export class Monster {
    constructor(id, wave, path) {
        this.id = id; this.wave = wave;
        this.maxHp = Math.floor(MONSTER_BASE_HP * Math.pow(MONSTER_HP_SCALE, wave - 1));
        this.hp = this.maxHp;
        this.speed = MONSTER_SPEED + wave * 0.05;
        this.path = path; this.wpIdx = 0;
        this.x = path[0].x; this.y = path[0].y;
        this.dead = false; this.reached = false;
        this.goldValue = wave;
    }
    move() {
        if (this.dead || this.reached) return;
        const tgt = this.path[this.wpIdx + 1];
        if (!tgt) { this.reached = true; return; }
        const dx = tgt.x - this.x, dy = tgt.y - this.y;
        const d = Math.hypot(dx, dy);
        if (d < this.speed) { this.x = tgt.x; this.y = tgt.y; this.wpIdx++; }
        else { this.x += (dx / d) * this.speed; this.y += (dy / d) * this.speed; }
    }
}

// ── Bullet ───────────────────────────────────────────────────────────────────
export class Bullet {
    constructor(tower, target) {
        this.x = tower.x; this.y = tower.y;
        this.target = target;
        this.color = TOWER_TYPES[tower.type].color;
        this.speed = TOWER_TYPES[tower.type].bulletSpeed;
        this.damage = tower.damage;
        this.done = false;
    }
    move() {
        if (this.target.dead || this.target.reached) { this.done = true; return; }
        const dx = this.target.x - this.x, dy = this.target.y - this.y;
        const d = Math.hypot(dx, dy);
        if (d < this.speed + 4) {
            this.target.hp -= this.damage;
            if (this.target.hp <= 0) this.target.dead = true;
            this.done = true;
        } else {
            this.x += (dx / d) * this.speed;
            this.y += (dy / d) * this.speed;
        }
    }
}

// ── Engine ───────────────────────────────────────────────────────────────────
export class GameEngine {
    constructor() { this.reset(); }

    reset() {
        this.grid         = MAP_GRID.map(r => [...r]);
        this.path         = buildPath();
        this.towers       = {};
        this.monsters     = [];
        this.bullets      = [];
        this.gold         = STARTING_GOLD;
        this.lives        = STARTING_LIVES;
        this.wave         = 0;
        this.score        = 0;
        this.running      = false;
        this.gameOver     = false;
        this.waveActive   = false;
        this.spawned      = 0;
        this.lastSpawn    = 0;
        this.nextId       = 0;
        this.selectedKey  = null;
        this.pendingType  = null;
        this.uiButtons    = [];
        this.countdown    = null;
        this.lastUpdateTime = null;
    }

    get selectedTower() {
        return this.selectedKey ? this.towers[this.selectedKey] : null;
    }

    canPlace(col, row) { return this.grid[row]?.[col] === 0; }

    place(col, row, type) {
        const cost = TOWER_TYPES[type].cost;
        if (!this.canPlace(col, row) || this.gold < cost) return false;
        this.gold -= cost;
        this.grid[row][col] = 2;
        const key = `${col},${row}`;
        this.towers[key] = new Tower(col, row, type);
        return true;
    }

    upgrade(key) {
        const t = this.towers[key];
        if (!t || t.upgradeCost === null || this.gold < t.upgradeCost) return false;
        this.gold -= t.upgradeCost;
        t.upgrade();
        return true;
    }

    sell(key) {
        const t = this.towers[key];
        if (!t) return false;
        const base = TOWER_TYPES[t.type].cost;
        let spent = 0;
        for (let i = 0; i < t.upgradeLevel; i++) spent += UPGRADE_LEVELS[i].cost;
        this.gold += Math.floor(base * 0.5 + spent * 0.3);
        this.grid[t.row][t.col] = 0;
        delete this.towers[key];
        if (this.selectedKey === key) this.selectedKey = null;
        return true;
    }

    startWave() {
        if (this.waveActive || this.gameOver) return;
        this.countdown = null;
        this.wave++;
        this.waveActive = true;
        this.spawned = 0;
        this.lastSpawn = performance.now();
        this.running = true;
    }

    update(now) {
        if (this.gameOver) return;

        // Countdown to auto-start next wave
        if (!this.waveActive && this.countdown !== null) {
            const delta = this.lastUpdateTime ? now - this.lastUpdateTime : 16;
            this.countdown -= delta;
            if (this.countdown <= 0) this.startWave();
        }
        this.lastUpdateTime = now;

        if (!this.running) return;

        if (this.waveActive && this.spawned < MONSTERS_PER_WAVE) {
            if (now - this.lastSpawn >= MONSTER_SPAWN_MS) {
                this.monsters.push(new Monster(this.nextId++, this.wave, this.path));
                this.spawned++;
                this.lastSpawn = now;
            }
        }

        for (const m of this.monsters) {
            m.move();
            if (m.reached) {
                this.lives--;
                m.dead = true;
                if (this.lives <= 0) { this.gameOver = true; this.running = false; }
            }
        }

        for (const t of Object.values(this.towers)) {
            TOWER_TYPES[t.type].melee ? this._melee(t, now) : this._ranged(t, now);
        }

        for (const b of this.bullets) b.move();

        for (const m of this.monsters) {
            if (m.dead && m.goldValue > 0) {
                this.gold += m.goldValue;
                this.score += m.goldValue;
                m.goldValue = 0;
            }
        }

        this.bullets  = this.bullets.filter(b => !b.done);
        this.monsters = this.monsters.filter(m => !m.dead);

        if (this.waveActive && this.spawned >= MONSTERS_PER_WAVE && this.monsters.length === 0) {
            this.waveActive = false;
            if (!this.gameOver) this.countdown = WAVE_COUNTDOWN_MS;
        }
    }

    _closest(tower) {
        let best = null, bestProgress = -1;
        for (const m of this.monsters) {
            if (m.dead || m.reached) continue;
            if (dist(tower, m) > tower.range) continue;
            const progress = m.wpIdx + (m.wpIdx < m.path.length - 1
                ? 1 - dist(m, m.path[m.wpIdx + 1]) / (dist(m.path[m.wpIdx], m.path[m.wpIdx + 1]) || 1)
                : 1);
            if (progress > bestProgress) { bestProgress = progress; best = m; }
        }
        return best;
    }

    _ranged(tower, now) {
        if (now - tower.lastFired < tower.fireRate) return;
        const t = this._closest(tower);
        if (!t) return;
        this.bullets.push(new Bullet(tower, t));
        tower.lastFired = now;
    }

    _melee(tower, now) {
        if (now - tower.lastFired < tower.fireRate) return;
        const inRange = this.monsters.filter(m => !m.dead && !m.reached && dist(tower, m) <= tower.range);
        if (!inRange.length) return;
        for (const m of inRange) {
            m.hp -= tower.damage;
            if (m.hp <= 0) m.dead = true;
        }
        tower.lastFired = now;
        tower.swinging = true; tower.swingAngle = 0; tower.swingDir = 1;
    }
}
