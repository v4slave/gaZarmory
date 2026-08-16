<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('loot_imports', function (Blueprint $table): void {
            $table->id(); $table->foreignId('activity_id')->constrained(); $table->foreignId('created_by')->constrained('users');
            $table->string('source_type', 20); $table->string('original_filename'); $table->string('file_hash', 64);
            $table->string('status', 20)->default('draft'); $table->text('error_message')->nullable();
            $table->timestampTz('confirmed_at')->nullable(); $table->timestampsTz();
            $table->unique(['activity_id', 'file_hash']);
        });
        Schema::create('loot_import_rows', function (Blueprint $table): void {
            $table->id(); $table->foreignId('loot_import_id')->constrained('loot_imports')->cascadeOnDelete();
            $table->unsignedInteger('row_number'); $table->string('item_name'); $table->unsignedBigInteger('quantity');
            $table->unsignedBigInteger('unit_price'); $table->string('status', 20)->default('valid'); $table->jsonb('raw_data')->nullable();
            $table->timestampsTz(); $table->unique(['loot_import_id', 'row_number']);
        });
    }
    public function down(): void { Schema::dropIfExists('loot_import_rows'); Schema::dropIfExists('loot_imports'); }
};
