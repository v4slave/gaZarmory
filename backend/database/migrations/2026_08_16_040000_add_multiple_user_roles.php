<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->jsonb('roles')->default(DB::raw("'[\"member\"]'::jsonb"));
        });
        DB::statement("UPDATE users SET roles = jsonb_build_array(role::text)");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_roles_is_array CHECK (jsonb_typeof(roles) = 'array')");
        DB::statement('CREATE INDEX users_roles_gin_idx ON users USING GIN (roles)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_roles_gin_idx');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('roles'));
    }
};
