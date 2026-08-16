<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('activity_loot', fn (Blueprint $table) => $table->string('icon_path')->nullable());
        Schema::table('treasury_items', fn (Blueprint $table) => $table->string('icon_path')->nullable());
    }

    public function down(): void
    {
        Schema::table('activity_loot', fn (Blueprint $table) => $table->dropColumn('icon_path'));
        Schema::table('treasury_items', fn (Blueprint $table) => $table->dropColumn('icon_path'));
    }
};
