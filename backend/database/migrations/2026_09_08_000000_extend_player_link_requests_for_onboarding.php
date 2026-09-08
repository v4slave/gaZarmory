<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('player_link_requests', function (Blueprint $table): void { $table->foreignId('requested_group_id')->nullable()->after('player_id')->constrained('groups')->nullOnDelete(); $table->boolean('created_by_applicant')->default(false)->after('requested_group_id'); }); }
    public function down(): void { Schema::table('player_link_requests', function (Blueprint $table): void { $table->dropForeign(['requested_group_id']); $table->dropColumn(['requested_group_id', 'created_by_applicant']); }); }
};
