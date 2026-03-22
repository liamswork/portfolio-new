<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Kirkconnel game presence channel
// Returns user info so all clients know who's in the game
Broadcast::channel('kirkconnel.game.{gameId}', function ($user, $gameId) {
    $player = \App\Models\KirkconnelGamePlayer::where('game_id', $gameId)
        ->where('user_id', $user->id)
        ->first();

    if (!$player) return false;

    return [
        'id'         => $user->id,
        'name'       => $user->name,
        'color'      => $player->color,
        'turn_order' => $player->turn_order,
    ];
});
