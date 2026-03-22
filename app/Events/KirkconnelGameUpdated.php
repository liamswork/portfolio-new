<?php

namespace App\Events;

use App\Models\KirkconnelGame;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KirkconnelGameUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public KirkconnelGame $game) {}

    public function broadcastOn(): array
    {
        return [new PresenceChannel('kirkconnel.game.' . $this->game->id)];
    }

    public function broadcastAs(): string
    {
        return 'game.updated';
    }

    public function broadcastWith(): array
    {
        $this->game->load('players.user');
        return [
            'game'    => $this->game->only(['id','status','current_turn','round','state','winner_id']),
            'players' => $this->game->players->map(fn($p) => [
                'id'             => $p->id,
                'user_id'        => $p->user_id,
                'name'           => $p->user->name,
                'turn_order'     => $p->turn_order,
                'color'          => $p->color,
                'connected'      => $p->connected,
                'reinforcements' => $p->reinforcements,
                'eliminated'     => $p->eliminated,
            ]),
        ];
    }
}
