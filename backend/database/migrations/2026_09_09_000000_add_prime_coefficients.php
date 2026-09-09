<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('activities', fn (Blueprint $table) => $table->decimal('prime_coefficient', 5, 2)->default(1.00));
        Schema::table('activity_players', fn (Blueprint $table) => $table->decimal('prime_coefficient', 5, 2)->default(1.00));
    }

    public function down(): void
    {
        Schema::table('activity_players', fn (Blueprint $table) => $table->dropColumn('prime_coefficient'));
        Schema::table('activities', fn (Blueprint $table) => $table->dropColumn('prime_coefficient'));
    }
};
