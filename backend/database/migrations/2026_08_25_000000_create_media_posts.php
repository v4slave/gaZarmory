<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('media_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->string('kind', 16);
            $table->string('provider', 24)->nullable();
            $table->text('source_url')->nullable();
            $table->text('embed_url')->nullable();
            $table->string('file_path')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->timestamps();
        });

        Schema::create('media_reactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->timestamps();
            $table->unique(['media_post_id', 'user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_reactions');
        Schema::dropIfExists('media_posts');
    }
};
