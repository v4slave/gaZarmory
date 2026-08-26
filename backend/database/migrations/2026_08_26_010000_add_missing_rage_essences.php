<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $source = DB::table('loot_catalog_items')->where('name', 'Эссенция ярости х1000')->first();
        if (!$source) return;

        $now = now();
        foreach ([2000, 4500, 12500] as $amount) {
            DB::table('loot_catalog_items')->updateOrInsert(
                ['name' => 'Эссенция ярости х'.$amount],
                [
                    'icon_path' => $source->icon_path,
                    'rarity' => $source->rarity ?? 'common',
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('loot_catalog_items')->whereIn('name', [
            'Эссенция ярости х2000',
            'Эссенция ярости х4500',
            'Эссенция ярости х12500',
        ])->delete();
    }
};
