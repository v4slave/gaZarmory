<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            $table->string('archa_gear_url', 255)->nullable();
            $table->json('archa_gear_items')->nullable();
            $table->timestampTz('archa_gear_updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('players', fn (Blueprint $table) => $table->dropColumn(['archa_gear_url', 'archa_gear_items', 'archa_gear_updated_at']));
    }
};
