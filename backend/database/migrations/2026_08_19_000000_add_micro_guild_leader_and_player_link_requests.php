<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TYPE user_role ADD VALUE IF NOT EXISTS 'micro_guild_leader'");

        Schema::create('player_link_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();
            $table->index(['status', 'created_at']);
        });

        DB::statement("ALTER TABLE player_link_requests ADD CONSTRAINT player_link_requests_status_check CHECK (status IN ('pending', 'approved', 'rejected'))");
        DB::statement("CREATE UNIQUE INDEX player_link_requests_pending_user_unique ON player_link_requests (user_id) WHERE status = 'pending'");
        DB::statement("CREATE UNIQUE INDEX player_link_requests_pending_player_unique ON player_link_requests (player_id) WHERE status = 'pending'");
    }

    public function down(): void
    {
        Schema::dropIfExists('player_link_requests');
    }
};
