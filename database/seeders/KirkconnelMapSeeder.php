<?php

namespace Database\Seeders;

use App\Models\KirkconnelMap;
use App\Models\KirkconnelMapPolygon;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KirkconnelMapSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'player1@kirkconnel.test')->first();
        if (!$user) return;

        if (KirkconnelMap::where('name', 'Classic')->exists()) return;

        $map = KirkconnelMap::create([
            'created_by'  => $user->id,
            'name'        => 'Classic',
            'description' => 'A balanced starter map with polygon territories.',
            'published'   => true,
            'continents'  => [
                ['id' => 'north', 'name' => 'North',  'bonus' => 3, 'color' => '#1e3a5f'],
                ['id' => 'south', 'name' => 'South',  'bonus' => 3, 'color' => '#3b1f1f'],
                ['id' => 'east',  'name' => 'East',   'bonus' => 2, 'color' => '#1f3b1f'],
                ['id' => 'west',  'name' => 'West',   'bonus' => 2, 'color' => '#3b3b1f'],
                ['id' => 'mid',   'name' => 'Middle', 'bonus' => 4, 'color' => '#2d1f3b'],
            ],
        ]);

        // Each polygon is a rough quadrilateral region on a 680×520 canvas
        $polygonDefs = [
            // North
            ['name'=>'Ashford',    'continent'=>'north', 'vertices'=>[['x'=>80,'y'=>20],['x'=>200,'y'=>20],['x'=>210,'y'=>120],['x'=>90,'y'=>130]]],
            ['name'=>'Brindal',    'continent'=>'north', 'vertices'=>[['x'=>200,'y'=>20],['x'=>330,'y'=>20],['x'=>330,'y'=>110],['x'=>210,'y'=>120]]],
            ['name'=>'Coldmere',   'continent'=>'north', 'vertices'=>[['x'=>90,'y'=>130],['x'=>210,'y'=>120],['x'=>220,'y'=>200],['x'=>100,'y'=>210]]],
            ['name'=>'Dunvale',    'continent'=>'north', 'vertices'=>[['x'=>210,'y'=>120],['x'=>330,'y'=>110],['x'=>340,'y'=>200],['x'=>220,'y'=>200]]],
            ['name'=>'Edgewick',   'continent'=>'north', 'vertices'=>[['x'=>330,'y'=>20],['x'=>460,'y'=>20],['x'=>460,'y'=>110],['x'=>330,'y'=>110]]],
            ['name'=>'Fenwick',    'continent'=>'north', 'vertices'=>[['x'=>460,'y'=>20],['x'=>580,'y'=>20],['x'=>580,'y'=>130],['x'=>460,'y'=>110]]],
            // South
            ['name'=>'Greystone',  'continent'=>'south', 'vertices'=>[['x'=>80,'y'=>370],['x'=>200,'y'=>360],['x'=>210,'y'=>460],['x'=>80,'y'=>500]]],
            ['name'=>'Harrow',     'continent'=>'south', 'vertices'=>[['x'=>200,'y'=>360],['x'=>330,'y'=>370],['x'=>330,'y'=>500],['x'=>210,'y'=>460]]],
            ['name'=>'Ironhold',   'continent'=>'south', 'vertices'=>[['x'=>100,'y'=>290],['x'=>220,'y'=>280],['x'=>200,'y'=>360],['x'=>80,'y'=>370]]],
            ['name'=>'Jestwick',   'continent'=>'south', 'vertices'=>[['x'=>220,'y'=>280],['x'=>340,'y'=>290],['x'=>330,'y'=>370],['x'=>200,'y'=>360]]],
            ['name'=>'Keldmoor',   'continent'=>'south', 'vertices'=>[['x'=>330,'y'=>370],['x'=>460,'y'=>360],['x'=>460,'y'=>500],['x'=>330,'y'=>500]]],
            ['name'=>'Lochvale',   'continent'=>'south', 'vertices'=>[['x'=>460,'y'=>360],['x'=>580,'y'=>370],['x'=>580,'y'=>500],['x'=>460,'y'=>500]]],
            // East
            ['name'=>'Morwick',    'continent'=>'east',  'vertices'=>[['x'=>580,'y'=>20],['x'=>660,'y'=>20],['x'=>660,'y'=>200],['x'=>580,'y'=>130]]],
            ['name'=>'Northfen',   'continent'=>'east',  'vertices'=>[['x'=>580,'y'=>130],['x'=>660,'y'=>200],['x'=>660,'y'=>340],['x'=>580,'y'=>280]]],
            ['name'=>'Ostmark',    'continent'=>'east',  'vertices'=>[['x'=>580,'y'=>280],['x'=>660,'y'=>340],['x'=>660,'y'=>500],['x'=>580,'y'=>370]]],
            // West
            ['name'=>'Pinehurst',  'continent'=>'west',  'vertices'=>[['x'=>20,'y'=>20],['x'=>80,'y'=>20],['x'=>90,'y'=>130],['x'=>20,'y'=>210]]],
            ['name'=>'Queensmere', 'continent'=>'west',  'vertices'=>[['x'=>20,'y'=>210],['x'=>90,'y'=>130],['x'=>100,'y'=>290],['x'=>20,'y'=>370]]],
            ['name'=>'Ravenfall',  'continent'=>'west',  'vertices'=>[['x'=>20,'y'=>370],['x'=>100,'y'=>290],['x'=>80,'y'=>370],['x'=>20,'y'=>500]]],
            // Middle
            ['name'=>'Stonegate',  'continent'=>'mid',   'vertices'=>[['x'=>100,'y'=>210],['x'=>220,'y'=>200],['x'=>220,'y'=>280],['x'=>100,'y'=>290]]],
            ['name'=>'Thornvale',  'continent'=>'mid',   'vertices'=>[['x'=>220,'y'=>200],['x'=>340,'y'=>200],['x'=>340,'y'=>290],['x'=>220,'y'=>280]]],
            ['name'=>'Umbridge',   'continent'=>'mid',   'vertices'=>[['x'=>340,'y'=>200],['x'=>460,'y'=>200],['x'=>460,'y'=>360],['x'=>340,'y'=>290]]],
        ];

        $created = [];
        foreach ($polygonDefs as $def) {
            $created[] = KirkconnelMapPolygon::create([
                'map_id'    => $map->id,
                'name'      => $def['name'],
                'continent' => $def['continent'],
                'vertices'  => $def['vertices'],
            ]);
        }

        // Index by name for easy lookup
        $byName = collect($created)->keyBy('name');

        // Adjacency list (mirrors the old territory connections)
        $adjacencies = [
            'Ashford'    => ['Brindal','Coldmere','Pinehurst'],
            'Brindal'    => ['Ashford','Coldmere','Dunvale'],
            'Coldmere'   => ['Ashford','Brindal','Dunvale','Stonegate'],
            'Dunvale'    => ['Brindal','Coldmere','Edgewick','Morwick'],
            'Edgewick'   => ['Dunvale','Fenwick','Morwick'],
            'Fenwick'    => ['Edgewick','Morwick','Northfen'],
            'Greystone'  => ['Harrow','Ironhold','Ravenfall'],
            'Harrow'     => ['Greystone','Ironhold','Jestwick'],
            'Ironhold'   => ['Greystone','Harrow','Jestwick','Umbridge'],
            'Jestwick'   => ['Harrow','Ironhold','Keldmoor','Ostmark'],
            'Keldmoor'   => ['Jestwick','Lochvale','Ostmark'],
            'Lochvale'   => ['Keldmoor','Ostmark','Northfen'],
            'Morwick'    => ['Dunvale','Edgewick','Fenwick','Northfen'],
            'Northfen'   => ['Morwick','Fenwick','Lochvale','Ostmark'],
            'Ostmark'    => ['Northfen','Jestwick','Keldmoor','Lochvale'],
            'Pinehurst'  => ['Ashford','Queensmere'],
            'Queensmere' => ['Pinehurst','Ravenfall','Stonegate'],
            'Ravenfall'  => ['Queensmere','Greystone'],
            'Stonegate'  => ['Coldmere','Queensmere','Thornvale','Umbridge'],
            'Thornvale'  => ['Stonegate','Umbridge','Dunvale','Northfen'],
            'Umbridge'   => ['Stonegate','Thornvale','Ironhold','Jestwick'],
        ];

        $seen = [];
        foreach ($adjacencies as $nameA => $neighbours) {
            foreach ($neighbours as $nameB) {
                $idA = $byName[$nameA]->id;
                $idB = $byName[$nameB]->id;
                [$lo, $hi] = $idA < $idB ? [$idA, $idB] : [$idB, $idA];
                $key = "{$lo}-{$hi}";
                if (isset($seen[$key])) continue;
                $seen[$key] = true;
                DB::table('kirkconnel_map_polygon_connections')->insert([
                    'map_id'       => $map->id,
                    'polygon_a_id' => $lo,
                    'polygon_b_id' => $hi,
                ]);
            }
        }
    }
}
