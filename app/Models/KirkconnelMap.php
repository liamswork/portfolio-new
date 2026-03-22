<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KirkconnelMap extends Model
{
    protected $table = 'kirkconnel_maps';

    protected $fillable = ['created_by', 'name', 'description', 'continents', 'published'];

    protected $casts = [
        'continents' => 'array',
        'published'  => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function games(): HasMany
    {
        return $this->hasMany(KirkconnelGame::class, 'map_id');
    }

    public function polygons(): HasMany
    {
        return $this->hasMany(KirkconnelMapPolygon::class, 'map_id');
    }
}
