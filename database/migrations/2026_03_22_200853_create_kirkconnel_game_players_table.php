<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kirkconnel_game_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained('kirkconnel_games')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('turn_order');          // 0-3
            $table->string('color');                // player colour hex
            $table->string('session_token')->unique(); // for reconnection
            $table->boolean('connected')->default(false);
            $table->integer('reinforcements')->default(0);
            $table->boolean('eliminated')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kirkconnel_game_players');
    }
};
