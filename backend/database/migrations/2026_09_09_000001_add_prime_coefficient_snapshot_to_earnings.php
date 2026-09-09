<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('prime_player_earnings', function (Blueprint $table): void {
            $table->decimal('prime_coefficient_snapshot', 8, 4)->default(1.0000);
        });
    }

    public function down(): void
    {
        Schema::table('prime_player_earnings', function (Blueprint $table): void {
            $table->dropColumn('prime_coefficient_snapshot');
        });
    }
};
