<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KirkconnelMapPolygon extends Model
{
    protected $table = 'kirkconnel_map_polygons';

    protected $fillable = ['map_id', 'name', 'continent', 'vertices'];

    protected $casts = [
        'vertices' => 'array',
    ];

    public function map(): BelongsTo
    {
        return $this->belongsTo(KirkconnelMap::class, 'map_id');
    }

    /** Compute centroid of the polygon for label placement */
    public function centroid(): array
    {
        $verts = $this->vertices;
        $x = array_sum(array_column($verts, 'x')) / count($verts);
        $y = array_sum(array_column($verts, 'y')) / count($verts);
        return ['x' => $x, 'y' => $y];
    }
}
