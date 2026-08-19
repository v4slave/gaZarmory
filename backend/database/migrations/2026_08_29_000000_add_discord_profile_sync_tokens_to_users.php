<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('discord_access_token')->nullable();
            $table->text('discord_refresh_token')->nullable();
            $table->timestamp('discord_token_expires_at')->nullable();
            $table->timestamp('discord_synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['discord_access_token', 'discord_refresh_token', 'discord_token_expires_at', 'discord_synced_at']);
        });
    }
};
