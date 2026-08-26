<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loot_catalog_items', fn (Blueprint $table) => $table->string('rarity', 24)->default('common')->after('icon_path'));
        Schema::table('activity_loot', fn (Blueprint $table) => $table->string('rarity', 24)->default('common')->after('icon_path'));
        Schema::table('treasury_items', fn (Blueprint $table) => $table->string('rarity', 24)->default('common')->after('icon_path'));
    }

    public function down(): void
    {
        Schema::table('loot_catalog_items', fn (Blueprint $table) => $table->dropColumn('rarity'));
        Schema::table('activity_loot', fn (Blueprint $table) => $table->dropColumn('rarity'));
        Schema::table('treasury_items', fn (Blueprint $table) => $table->dropColumn('rarity'));
    }
};
