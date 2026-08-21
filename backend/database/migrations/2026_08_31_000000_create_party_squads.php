<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('party_squads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['group_id', 'name']);
        });

        Schema::create('party_squad_players', function (Blueprint $table): void {
            $table->foreignId('party_squad_id')->constrained('party_squads')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['party_squad_id', 'player_id']);
            $table->unique('player_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_squad_players');
        Schema::dropIfExists('party_squads');
    }
};
