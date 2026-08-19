<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function (): void {
            $definitionIds = DB::table('activity_definitions')->whereIn('name', ['Марля', 'Т2 Марля'])->pluck('id');
            if ($definitionIds->isEmpty()) return;
            $activityIds = DB::table('activities')->whereIn('activity_definition_id', $definitionIds)->pluck('id');
            if ($activityIds->isNotEmpty()) {
                DB::table('treasury_item_transactions')->whereIn('source_activity_id', $activityIds)->update(['source_activity_id' => null]);
                DB::table('payout_activities')->whereIn('activity_id', $activityIds)->delete();
                DB::table('prime_player_earnings')->whereIn('activity_id', $activityIds)->delete();
                DB::table('activity_loot')->whereIn('activity_id', $activityIds)->delete();
                DB::table('loot_imports')->whereIn('activity_id', $activityIds)->delete();
                DB::table('activity_players')->whereIn('activity_id', $activityIds)->delete();
                DB::table('activities')->whereIn('id', $activityIds)->delete();
            }
            DB::table('activity_definitions')->whereIn('id', $definitionIds)->delete();
        });
    }

    public function down(): void
    {
        $now = now();
        foreach (['Марля', 'Т2 Марля'] as $name) {
            DB::table('activity_definitions')->insertOrIgnore(['name' => $name, 'type' => 'mini_activity', 'is_active' => false, 'created_at' => $now, 'updated_at' => $now]);
        }
    }
};
