<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::statement(<<<'SQL'
                UPDATE activities AS activity
                SET gold_value = totals.gold_value
                FROM (
                    SELECT activity_id, COALESCE(SUM(quantity * unit_price), 0)::bigint AS gold_value
                    FROM activity_loot
                    GROUP BY activity_id
                ) AS totals
                WHERE activity.id = totals.activity_id
                  AND activity.completed_at IS NOT NULL
                  AND EXISTS (
                      SELECT 1 FROM activity_definitions definition
                      WHERE definition.id = activity.activity_definition_id
                        AND definition.type = 'mini_activity'
                  )
                SQL);

            DB::statement(<<<'SQL'
                INSERT INTO prime_player_earnings (
                    activity_id, player_id, nickname_snapshot,
                    prime_gold_value_snapshot, participants_count_snapshot,
                    player_share, status, created_at
                )
                SELECT
                    activity.id,
                    activity_player.player_id,
                    player.nickname,
                    COALESCE(loot.gold_value, 0),
                    participants.participants_count,
                    FLOOR(COALESCE(loot.gold_value, 0)::numeric / participants.participants_count)::bigint,
                    'pending',
                    NOW()
                FROM activities activity
                JOIN activity_definitions definition ON definition.id = activity.activity_definition_id
                JOIN activity_players activity_player ON activity_player.activity_id = activity.id
                JOIN players player ON player.id = activity_player.player_id
                JOIN (
                    SELECT activity_id, COUNT(*)::integer AS participants_count
                    FROM activity_players GROUP BY activity_id
                ) participants ON participants.activity_id = activity.id
                LEFT JOIN (
                    SELECT activity_id, SUM(quantity * unit_price)::bigint AS gold_value
                    FROM activity_loot GROUP BY activity_id
                ) loot ON loot.activity_id = activity.id
                WHERE definition.type = 'mini_activity'
                  AND activity.completed_at IS NOT NULL
                ON CONFLICT (activity_id, player_id) DO NOTHING
                SQL);
        });
    }

    public function down(): void
    {
        // Исторические начисления намеренно не удаляются при откате.
    }
};
