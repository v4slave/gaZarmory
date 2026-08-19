<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private const ASSET_COLUMNS = [
        'has_ship', 'has_tank', 'has_fuchsias', 'has_clouds', 'has_machaon',
        'has_tare', 'has_deer', 'has_invulnerable_pet', 'has_shield_swap', 'has_flippers',
    ];

    public function up(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            $table->unsignedInteger('gear_score')->default(0);
            foreach (self::ASSET_COLUMNS as $column) $table->boolean($column)->default(false);
        });

        DB::table('activity_definitions')
            ->whereIn('name', ['Марля', 'Т2 Марля', 'Жук'])
            ->update(['is_active' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            $table->dropColumn(array_merge(['gear_score'], self::ASSET_COLUMNS));
        });
    }
};
