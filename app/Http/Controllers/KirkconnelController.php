<?php

namespace App\Http\Controllers;

use App\Events\KirkconnelGameUpdated;
use App\Models\KirkconnelGame;
use App\Models\KirkconnelGamePlayer;
use App\Models\KirkconnelMap;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class KirkconnelController extends Controller
{
    // ── Lobby ────────────────────────────────────────────────────────────────

    public function lobby()
    {
        $maps  = KirkconnelMap::where('published', true)->with('creator:id,name')->get();
        $games = KirkconnelGame::where('status', 'waiting')
            ->with(['creator:id,name', 'players.user:id,name', 'map:id,name'])
            ->get();

        return Inertia::render('Games/Kirkconnel/Lobby', [
            'maps'  => $maps,
            'games' => $games,
            'auth'  => ['user' => Auth::user()],
        ]);
    }

    // ── Create game ──────────────────────────────────────────────────────────

    public function create(Request $request)
    {
        $request->validate(['map_id' => 'required|exists:kirkconnel_maps,id']);

        $map   = KirkconnelMap::with('polygons')->findOrFail($request->map_id);
        $state = $this->buildInitialState($map);

        $game = KirkconnelGame::create([
            'map_id'       => $map->id,
            'created_by'   => Auth::id(),
            'status'       => 'waiting',
            'max_players'  => 4,
            'current_turn' => 0,
            'round'        => 1,
            'state'        => $state,
        ]);

        $this->addPlayer($game, Auth::id(), 0);

        return redirect()->route('kirkconnel.game', $game->id);
    }

    // ── Join game ────────────────────────────────────────────────────────────

    public function join(Request $request, KirkconnelGame $game)
    {
        if ($game->status !== 'waiting') {
            return back()->withErrors(['game' => 'Game already started.']);
        }

        $existing = $game->players()->where('user_id', Auth::id())->first();
        if ($existing) {
            return redirect()->route('kirkconnel.game', $game->id);
        }

        $turnOrder = $game->players()->count();
        if ($turnOrder >= $game->max_players) {
            return back()->withErrors(['game' => 'Game is full.']);
        }

        $this->addPlayer($game, Auth::id(), $turnOrder);
        broadcast(new KirkconnelGameUpdated($game->fresh()))->toOthers();

        return redirect()->route('kirkconnel.game', $game->id);
    }

    // ── Game view ────────────────────────────────────────────────────────────

    public function show(KirkconnelGame $game)
    {
        $game->load(['map.polygons', 'players.user']);
        $player = $game->players()->where('user_id', Auth::id())->first();

        // Build adjacency map: polygon_id => [adjacent_polygon_id, ...]
        $connections = DB::table('kirkconnel_map_polygon_connections')
            ->where('map_id', $game->map_id)
            ->get();

        $adjacency = [];
        foreach ($connections as $conn) {
            $adjacency[$conn->polygon_a_id][] = $conn->polygon_b_id;
            $adjacency[$conn->polygon_b_id][] = $conn->polygon_a_id;
        }

        $polygons = $game->map->polygons->map(fn($p) => [
            'id'        => $p->id,
            'name'      => $p->name,
            'continent' => $p->continent,
            'vertices'  => $p->vertices,
            'centroid'  => $p->centroid(),
            'connections' => $adjacency[$p->id] ?? [],
        ]);

        return Inertia::render('Games/Kirkconnel/Game', [
            'game'     => $game,
            'map'      => [
                'id'         => $game->map->id,
                'name'       => $game->map->name,
                'continents' => $game->map->continents,
                'polygons'   => $polygons,
            ],
            'players'  => $game->players->map(fn($p) => [
                'id'             => $p->id,
                'user_id'        => $p->user_id,
                'name'           => $p->user->name,
                'turn_order'     => $p->turn_order,
                'color'          => $p->color,
                'connected'      => $p->connected,
                'reinforcements' => $p->reinforcements,
                'eliminated'     => $p->eliminated,
            ]),
            'myPlayer' => $player ? [
                'id'            => $player->id,
                'turn_order'    => $player->turn_order,
                'color'         => $player->color,
                'session_token' => $player->session_token,
            ] : null,
            'auth'     => ['user' => Auth::user()],
        ]);
    }

    // ── Start game ───────────────────────────────────────────────────────────

    public function start(KirkconnelGame $game)
    {
        if ($game->created_by !== Auth::id() || $game->status !== 'waiting') {
            abort(403);
        }

        $players = $game->players;
        if ($players->count() < 1) {
            return back()->withErrors(['game' => 'Need at least 1 player.']);
        }

        $state    = $game->state;
        $polygons = collect($state['polygons']);
        $playerIds = $players->pluck('user_id')->shuffle();
        $count     = $polygons->count();

        $polygons = $polygons->map(function ($p, $i) use ($playerIds) {
            $p['owner']  = $playerIds[$i % $playerIds->count()];
            $p['armies'] = 1;
            return $p;
        })->values()->all();

        $state['polygons'] = $polygons;
        $game->update(['status' => 'active', 'state' => $state]);

        // Spread 10 bonus armies randomly across each player's starting territories
        $state = $game->fresh()->state;
        foreach ($players as $player) {
            $owned = array_keys(array_filter($state['polygons'], fn($p) => $p['owner'] === $player->user_id));
            if (empty($owned)) continue;
            for ($i = 0; $i < 10; $i++) {
                $idx = $owned[array_rand($owned)];
                $state['polygons'][$idx]['armies']++;
            }
        }
        $game->update(['state' => $state]);

        // Give first player 7 armies to place
        $firstPlayer = $game->players()->where('turn_order', 0)->first();
        $firstPlayer->update(['reinforcements' => 7]);

        broadcast(new KirkconnelGameUpdated($game->fresh()));

        return redirect()->route('kirkconnel.game', $game->id);
    }

    // ── Reconnect ────────────────────────────────────────────────────────────

    public function reconnect(Request $request)
    {
        $request->validate(['session_token' => 'required|string']);
        $player = KirkconnelGamePlayer::where('session_token', $request->session_token)->firstOrFail();
        $player->update(['connected' => true]);

        broadcast(new KirkconnelGameUpdated($player->game->fresh()))->toOthers();

        return redirect()->route('kirkconnel.game', $player->game_id);
    }

    // ── Game actions ─────────────────────────────────────────────────────────

    public function action(Request $request, KirkconnelGame $game)
    {
        $player = $game->players()->where('user_id', Auth::id())->firstOrFail();

        if ($game->status !== 'active' || $game->current_turn !== $player->turn_order) {
            abort(403, 'Not your turn.');
        }

        $type       = $request->input('type');
        $lastAction = null;

        match ($type) {
            'place'   => $this->handlePlace($game, $player, $request),
            'attack'  => ($lastAction = $this->handleAttack($game, $player, $request)),
            'fortify' => $this->handleFortify($game, $player, $request),
            'endturn' => $this->handleEndTurn($game, $player),
            default   => abort(422, 'Unknown action'),
        };

        broadcast(new KirkconnelGameUpdated($game->fresh(), $lastAction));

        $updated = $game->fresh();
        $updated->load('players.user');

        return response()->json([
            'ok'         => true,
            'last_action' => $lastAction,
            'game'       => $updated->only(['id','status','current_turn','round','state','winner_id']),
            'players'    => $updated->players->map(fn($p) => [
                'id'             => $p->id,
                'user_id'        => $p->user_id,
                'name'           => $p->user->name,
                'turn_order'     => $p->turn_order,
                'color'          => $p->color,
                'connected'      => $p->connected,
                'reinforcements' => $p->reinforcements,
                'eliminated'     => $p->eliminated,
            ]),
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function addPlayer(KirkconnelGame $game, int $userId, int $turnOrder): KirkconnelGamePlayer
    {
        $colors = ['#e94f37', '#3b82f6', '#22c55e', '#f59e0b'];
        return KirkconnelGamePlayer::create([
            'game_id'        => $game->id,
            'user_id'        => $userId,
            'turn_order'     => $turnOrder,
            'color'          => $colors[$turnOrder],
            'session_token'  => KirkconnelGamePlayer::generateToken(),
            'connected'      => true,
            'reinforcements' => 0,
        ]);
    }

    private function buildInitialState(KirkconnelMap $map): array
    {
        $polygons = $map->polygons->map(fn($p) => [
            'id'     => $p->id,
            'owner'  => null,
            'armies' => 0,
        ])->all();

        return ['polygons' => $polygons];
    }

    private function handlePlace(KirkconnelGame $game, KirkconnelGamePlayer $player, Request $request): void
    {
        $request->validate(['polygon_id' => 'required', 'armies' => 'required|integer|min:1']);

        $armies = (int) $request->armies;
        if ($armies > $player->reinforcements) abort(422, 'Not enough reinforcements.');

        $state = $game->state;
        $idx   = collect($state['polygons'])->search(fn($p) => $p['id'] == $request->polygon_id);
        if ($idx === false || $state['polygons'][$idx]['owner'] !== $player->user_id) abort(422);

        $state['polygons'][$idx]['armies'] += $armies;
        $player->decrement('reinforcements', $armies);
        $game->update(['state' => $state]);
    }

    private function handleAttack(KirkconnelGame $game, KirkconnelGamePlayer $player, Request $request): array
    {
        $request->validate(['from' => 'required', 'to' => 'required', 'armies' => 'required|integer|min:1']);

        $state    = $game->state;
        $polygons = &$state['polygons'];

        $fromIdx = collect($polygons)->search(fn($p) => $p['id'] == $request->from);
        $toIdx   = collect($polygons)->search(fn($p) => $p['id'] == $request->to);
        if ($fromIdx === false || $toIdx === false) abort(422);

        $from = &$polygons[$fromIdx];
        $to   = &$polygons[$toIdx];

        if ($from['owner'] !== $player->user_id) abort(403);
        if ($to['owner'] === $player->user_id) abort(422, 'Cannot attack own polygon.');

        // Verify adjacency via DB
        $adjacent = DB::table('kirkconnel_map_polygon_connections')
            ->where(function ($q) use ($request) {
                $q->where('polygon_a_id', $request->from)->where('polygon_b_id', $request->to);
            })
            ->orWhere(function ($q) use ($request) {
                $q->where('polygon_a_id', $request->to)->where('polygon_b_id', $request->from);
            })
            ->exists();

        if (!$adjacent) abort(422, 'Not adjacent.');

        $attackDice  = min((int) $request->armies, 3);
        $defendDice  = min($to['armies'], 2);
        $attackRolls = $this->rollDice($attackDice);
        $defendRolls = $this->rollDice($defendDice);

        rsort($attackRolls); rsort($defendRolls);

        $attackLoss = 0; $defendLoss = 0;
        $pairs = min(count($attackRolls), count($defendRolls));
        for ($i = 0; $i < $pairs; $i++) {
            if ($attackRolls[$i] > $defendRolls[$i]) $defendLoss++;
            else $attackLoss++;
        }

        $from['armies'] -= $attackLoss;
        $to['armies']   -= $defendLoss;

        if ($to['armies'] <= 0) {
            $to['owner']    = $player->user_id;
            $to['armies']   = $attackDice - $attackLoss;
            $from['armies'] -= ($attackDice - $attackLoss);
        }

        $game->update(['state' => $state]);

        $owners = collect($state['polygons'])->pluck('owner')->unique();
        if ($owners->count() === 1) {
            $game->update(['status' => 'finished', 'winner_id' => $player->user_id]);
        }

        return [
            'type'    => 'attack',
            'from'    => (int) $request->from,
            'to'      => (int) $request->to,
            'success' => $to['owner'] === $player->user_id,
        ];
    }

    private function handleFortify(KirkconnelGame $game, KirkconnelGamePlayer $player, Request $request): void
    {
        $request->validate(['from' => 'required', 'to' => 'required', 'armies' => 'required|integer|min:1']);

        $state    = $game->state;
        $polygons = &$state['polygons'];

        $fromIdx = collect($polygons)->search(fn($p) => $p['id'] == $request->from);
        $toIdx   = collect($polygons)->search(fn($p) => $p['id'] == $request->to);
        if ($fromIdx === false || $toIdx === false) abort(422);

        $from = &$polygons[$fromIdx];
        $to   = &$polygons[$toIdx];

        if ($from['owner'] !== $player->user_id || $to['owner'] !== $player->user_id) abort(403);

        $adjacent = DB::table('kirkconnel_map_polygon_connections')
            ->where(function ($q) use ($request) {
                $q->where('polygon_a_id', $request->from)->where('polygon_b_id', $request->to);
            })
            ->orWhere(function ($q) use ($request) {
                $q->where('polygon_a_id', $request->to)->where('polygon_b_id', $request->from);
            })
            ->exists();

        if (!$adjacent) abort(422, 'Not adjacent.');
        if ($from['armies'] - $request->armies < 1) abort(422, 'Must leave 1 army.');

        $from['armies'] -= $request->armies;
        $to['armies']   += $request->armies;

        $game->update(['state' => $state]);
    }

    private function handleEndTurn(KirkconnelGame $game, KirkconnelGamePlayer $player): void
    {
        $players    = $game->players()->where('eliminated', false)->orderBy('turn_order')->get();
        $currentIdx = $players->search(fn($p) => $p->turn_order === $game->current_turn);
        $nextIdx    = ($currentIdx + 1) % $players->count();
        $nextPlayer = $players[$nextIdx];

        $round = $game->round;
        if ($nextIdx === 0) $round++;

        $game->load('map.polygons');
        $reinforcements = $game->calculateReinforcements($nextPlayer);
        $nextPlayer->update(['reinforcements' => $reinforcements]);

        $game->update(['current_turn' => $nextPlayer->turn_order, 'round' => $round]);
    }

    private function rollDice(int $count): array
    {
        return array_map(fn() => random_int(1, 6), array_fill(0, $count, 0));
    }
}
