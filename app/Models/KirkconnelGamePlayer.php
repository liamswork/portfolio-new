<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class KirkconnelGamePlayer extends Model
{
    protected $table = 'kirkconnel_game_players';

    protected $fillable = [
        'game_id', 'user_id', 'turn_order', 'color',
        'session_token', 'connected', 'reinforcements', 'eliminated',
    ];

    protected $casts = [
        'connected'    => 'boolean',
        'eliminated'   => 'boolean',
    ];

    public static function generateToken(): string
    {
        return Str::random(64);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(KirkconnelGame::class, 'game_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
