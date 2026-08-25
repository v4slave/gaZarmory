<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('media_posts', function (Blueprint $table): void {
            $table->index(['kind', 'created_at']);
            $table->index('created_at');
        });
        Schema::table('media_reactions', function (Blueprint $table): void {
            $table->index(['media_post_id', 'type']);
            $table->index(['user_id', 'type', 'media_post_id']);
        });
    }

    public function down(): void
    {
        Schema::table('media_reactions', function (Blueprint $table): void {
            $table->dropIndex(['media_post_id', 'type']);
            $table->dropIndex(['user_id', 'type', 'media_post_id']);
        });
        Schema::table('media_posts', function (Blueprint $table): void {
            $table->dropIndex(['kind', 'created_at']);
            $table->dropIndex(['created_at']);
        });
    }
};
