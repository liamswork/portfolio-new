export const TILE_SIZE = 48;

export const TILE_LAND = 0;
export const TILE_PATH = 1;
export const TILE_TOWER = 2;

export const MAP_GRID = [
    [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
    [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
    [1,1,1,1,1,0,0,0,0,0,0,0,0,0,1,1,1,1,1,1],
    [0,0,0,0,1,0,0,0,0,0,0,0,0,0,1,0,0,0,0,1],
    [0,0,0,0,1,0,0,0,0,0,0,0,0,0,1,0,0,0,0,1],
    [0,0,0,0,1,1,1,1,1,1,1,1,1,1,1,0,0,0,0,1],
    [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1],
    [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1],
    [0,0,0,0,1,1,1,1,1,1,1,1,1,1,1,0,0,0,0,1],
    [0,0,0,0,1,0,0,0,0,0,0,0,0,0,1,0,0,0,0,1],
    [0,0,0,0,1,0,0,0,0,0,0,0,0,0,1,0,0,0,0,1],
    [1,1,1,1,1,0,0,0,0,0,0,0,0,0,1,1,1,1,1,1],
    [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
    [0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0],
];

export const PATH_WAYPOINTS = [
    [0,2],[4,2],[4,5],[14,5],[14,2],[19,2],
    [19,11],[14,11],[14,8],[4,8],[4,11],[0,11],
];

export const TOWER_TYPES = {
    blue:  { name:'Range',    color:'#3b82f6', circleColor:'#93c5fd', range:180, fireRate:1200, damage:12,  bulletSpeed:4, melee:false, cost:20  },
    green: { name:'Speed',    color:'#22c55e', circleColor:'#86efac', range:105, fireRate:400,  damage:8,  bulletSpeed:6, melee:false, cost:15  },
    red:   { name:'Balanced', color:'#ef4444', circleColor:'#fca5a5', range:135, fireRate:800,  damage:15, bulletSpeed:5, melee:false, cost:25  },
    black: { name:'Melee',    color:'#374151', circleColor:'#9ca3af', range:75,  fireRate:600,  damage:10, bulletSpeed:0, melee:true,  cost:40  },
};

export const UPGRADE_LEVELS = [
    { cost:10,  rangeBonus:20, fireRateMult:0.95, damageBonus:2  },
    { cost:40,  rangeBonus:35, fireRateMult:0.90, damageBonus:5  },
    { cost:80,  rangeBonus:55, fireRateMult:0.82, damageBonus:10 },
    { cost:160, rangeBonus:80, fireRateMult:0.70, damageBonus:18 },
    { cost:500, rangeBonus:120,fireRateMult:0.55, damageBonus:35 },
];

export const MONSTERS_PER_WAVE    = 10;
export const MONSTER_SPAWN_MS     = 800;
export const MONSTER_BASE_HP      = 60;
export const MONSTER_HP_SCALE     = 1.4;
export const MONSTER_SPEED        = 1.2;
export const STARTING_GOLD        = 100;
export const STARTING_LIVES       = 20;
export const WAVE_COUNTDOWN_MS    = 10000;

// Canvas layout
export const MAP_COLS  = MAP_GRID[0].length;
export const MAP_ROWS  = MAP_GRID.length;
export const MAP_W     = MAP_COLS * TILE_SIZE;
export const MAP_H     = MAP_ROWS * TILE_SIZE;
export const SIDEBAR_W = 220;
export const HUD_H     = 48;
export const CANVAS_W  = MAP_W + SIDEBAR_W;
export const CANVAS_H  = MAP_H + HUD_H;
