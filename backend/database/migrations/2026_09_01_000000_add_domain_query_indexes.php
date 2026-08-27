<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * PostgreSQL cannot create or drop concurrent indexes inside a transaction.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        $indexes = [
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS auctions_status_id_idx ON auctions (status, id DESC)',
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS auctions_status_ends_at_idx ON auctions (status, ends_at)',
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS payout_players_player_payout_idx ON payout_players (player_id, payout_id DESC)',
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS payout_players_payout_status_idx ON payout_players (payout_id, status, player_id)',
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS earnings_status_player_activity_idx ON prime_player_earnings (status, player_id, activity_id)',
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS earnings_payout_status_player_idx ON prime_player_earnings (payout_id, status, player_id)',
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS activity_players_player_activity_idx ON activity_players (player_id, activity_id)',
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS treasury_transactions_entity_idx ON treasury_transactions (related_entity_type, related_entity_id)',
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS treasury_item_transactions_item_created_idx ON treasury_item_transactions (treasury_item_id, created_at DESC)',
        ];

        foreach ($indexes as $index) {
            DB::statement($index);
        }
    }

    public function down(): void
    {
        $indexes = [
            'auctions_status_id_idx',
            'auctions_status_ends_at_idx',
            'payout_players_player_payout_idx',
            'payout_players_payout_status_idx',
            'earnings_status_player_activity_idx',
            'earnings_payout_status_player_idx',
            'activity_players_player_activity_idx',
            'treasury_transactions_entity_idx',
            'treasury_item_transactions_item_created_idx',
        ];

        foreach ($indexes as $index) {
            DB::statement("DROP INDEX CONCURRENTLY IF EXISTS {$index}");
        }
    }
};
