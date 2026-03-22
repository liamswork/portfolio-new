<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kirkconnel_map_polygons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('map_id')->constrained('kirkconnel_maps')->cascadeOnDelete();
            $table->string('name');
            $table->string('continent')->nullable();
            $table->json('vertices');   // [{x, y}, ...] ordered list of vertex coords
            $table->timestamps();
        });

        Schema::create('kirkconnel_map_polygon_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('map_id')->constrained('kirkconnel_maps')->cascadeOnDelete();
            $table->unsignedBigInteger('polygon_a_id');
            $table->unsignedBigInteger('polygon_b_id');
            $table->foreign('polygon_a_id')->references('id')->on('kirkconnel_map_polygons')->cascadeOnDelete();
            $table->foreign('polygon_b_id')->references('id')->on('kirkconnel_map_polygons')->cascadeOnDelete();
            $table->unique(['polygon_a_id', 'polygon_b_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kirkconnel_map_polygon_connections');
        Schema::dropIfExists('kirkconnel_map_polygons');
    }
};
