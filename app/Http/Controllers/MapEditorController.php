<?php

namespace App\Http\Controllers;

use App\Models\KirkconnelMap;
use App\Models\KirkconnelMapPolygon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MapEditorController extends Controller
{
    public function index()
    {
        return Inertia::render('Games/Kirkconnel/MapEditor', [
            'auth' => ['user' => Auth::user()],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'continents'  => 'required|array|min:1',
            'polygons'    => 'required|array|min:3',
            'polygons.*.name'      => 'required|string',
            'polygons.*.continent' => 'nullable|string',
            'polygons.*.vertices'  => 'required|array|min:3',
            'connections' => 'nullable|array',
            'connections.*.a' => 'required|integer',
            'connections.*.b' => 'required|integer',
        ]);

        DB::transaction(function () use ($request) {
            $map = KirkconnelMap::create([
                'created_by'  => Auth::id(),
                'name'        => $request->name,
                'description' => $request->description,
                'continents'  => $request->continents,
                'published'   => false,
            ]);

            // Map from client-side temp index → real DB id
            $indexToId = [];
            foreach ($request->polygons as $i => $poly) {
                $created = KirkconnelMapPolygon::create([
                    'map_id'    => $map->id,
                    'name'      => $poly['name'],
                    'continent' => $poly['continent'] ?? null,
                    'vertices'  => $poly['vertices'],
                ]);
                $indexToId[$i] = $created->id;
            }

            // Store connections (deduplicated, a < b)
            $seen = [];
            foreach ($request->connections ?? [] as $conn) {
                $a = $indexToId[$conn['a']] ?? null;
                $b = $indexToId[$conn['b']] ?? null;
                if (!$a || !$b || $a === $b) continue;
                [$lo, $hi] = $a < $b ? [$a, $b] : [$b, $a];
                $key = "{$lo}-{$hi}";
                if (isset($seen[$key])) continue;
                $seen[$key] = true;
                DB::table('kirkconnel_map_polygon_connections')->insert([
                    'map_id'       => $map->id,
                    'polygon_a_id' => $lo,
                    'polygon_b_id' => $hi,
                ]);
            }

            return $map;
        });

        return response()->json(['message' => 'Map saved. Pending publish.']);
    }

    public function publish(KirkconnelMap $map)
    {
        if ($map->created_by !== Auth::id()) abort(403);
        $map->update(['published' => true]);
        return response()->json(['ok' => true]);
    }
}
