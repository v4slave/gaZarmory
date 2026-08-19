<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('player_gear_score_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('gear_score');
            $table->timestampTz('recorded_at');
            $table->index(['player_id', 'recorded_at']);
        });

        DB::table('player_gear_score_history')->insertUsing(
            ['player_id', 'gear_score', 'recorded_at'],
            DB::table('players')->select('id', 'gear_score', DB::raw('CURRENT_TIMESTAMP'))
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('player_gear_score_history');
    }
};
