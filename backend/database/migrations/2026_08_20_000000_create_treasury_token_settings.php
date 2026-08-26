<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('treasury_token_settings')) {
            DB::statement(<<<'SQL'
                CREATE TABLE treasury_token_settings (
                    id SMALLINT PRIMARY KEY DEFAULT 1 CHECK (id = 1),
                    token_unit_value BIGINT NOT NULL DEFAULT 0 CHECK (token_unit_value >= 0),
                    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
                )
            SQL);
        }

        DB::table('treasury_token_settings')->insertOrIgnore(['id' => 1, 'token_unit_value' => 0]);
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_token_settings');
    }
};
