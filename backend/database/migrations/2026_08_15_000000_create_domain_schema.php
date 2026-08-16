<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $sql = file_get_contents(database_path('schema/domain.sql'));
        DB::unprepared(preg_replace('/^BEGIN;|COMMIT;$/m', '', $sql));
    }

    public function down(): void
    {
        throw new RuntimeException('Domain schema rollback is intentionally disabled; recreate the development database instead.');
    }
};

