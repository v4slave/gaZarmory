<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE users
            SET roles = COALESCE(
                (SELECT jsonb_agg(value) FROM jsonb_array_elements(roles) WHERE value <> '"officer"'::jsonb),
                '["member"]'::jsonb
            )
        SQL);

        DB::statement(<<<'SQL'
            UPDATE users
            SET role = CASE
                WHEN roles @> '["guild_leader"]'::jsonb THEN 'guild_leader'::user_role
                WHEN roles @> '["developer"]'::jsonb THEN 'developer'::user_role
                WHEN roles @> '["party_leader"]'::jsonb THEN 'party_leader'::user_role
                ELSE 'member'::user_role
            END
            WHERE role::text = 'officer'
        SQL);
    }

    public function down(): void
    {
        // Removed roles cannot be restored without knowing their previous owners.
    }
};
