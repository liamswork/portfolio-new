<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KirkconnelGame extends Model
{
    protected $table = 'kirkconnel_games';

    protected $fillable = [
        'map_id', 'created_by', 'status', 'max_players',
        'current_turn', 'round', 'state', 'winner_id',
    ];

    protected $casts = [
        'state' => 'array',
    ];

    public function map(): BelongsTo
    {
        return $this->belongsTo(KirkconnelMap::class, 'map_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function players(): HasMany
    {
        return $this->hasMany(KirkconnelGamePlayer::class, 'game_id')->orderBy('turn_order');
    }

    public function currentPlayer(): ?KirkconnelGamePlayer
    {
        return $this->players()->where('turn_order', $this->current_turn)->first();
    }

    /** Calculate reinforcements based on polygons owned + continent bonuses */
    public function calculateReinforcements(KirkconnelGamePlayer $player): int
    {
        $state    = $this->state;
        $polygons = collect($state['polygons']);
        $owned    = $polygons->where('owner', $player->user_id)->count();
        $base     = max(3, (int) floor($owned / 3));

        $continents    = $this->map->continents;
        $mapPolygons   = $this->map->polygons;
        $bonus = 0;

        foreach ($continents as $continent) {
            $continentPolygonIds = $mapPolygons
                ->where('continent', $continent['id'])
                ->pluck('id')
                ->all();

            $allOwned = collect($continentPolygonIds)->every(function ($pid) use ($polygons, $player) {
                $p = $polygons->firstWhere('id', $pid);
                return $p && $p['owner'] === $player->user_id;
            });

            if ($allOwned && count($continentPolygonIds) > 0) {
                $bonus += $continent['bonus'];
            }
        }

        return $base + $bonus;
    }
}
