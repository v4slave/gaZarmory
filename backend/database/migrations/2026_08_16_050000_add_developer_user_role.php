<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement("ALTER TYPE user_role ADD VALUE IF NOT EXISTS 'developer'");
    }

    public function down(): void
    {
        // PostgreSQL enum values cannot be removed safely without rebuilding the type.
    }
};
