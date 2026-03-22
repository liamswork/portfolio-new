<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kirkconnel_games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('map_id')->constrained('kirkconnel_maps')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('waiting'); // waiting|active|finished
            $table->integer('max_players')->default(4);
            $table->integer('current_turn')->default(0);
            $table->integer('round')->default(1);
            $table->json('state');   // {polygons: [{id, owner, armies}]}
            $table->string('winner_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kirkconnel_games');
    }
};
