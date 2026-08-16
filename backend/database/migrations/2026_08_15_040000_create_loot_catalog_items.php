<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up():void{Schema::create('loot_catalog_items',function(Blueprint $table){$table->id();$table->string('name')->unique();$table->string('icon_path')->nullable();$table->boolean('is_active')->default(true);$table->timestampsTz();});Schema::table('activity_loot',fn(Blueprint $table)=>$table->foreignId('loot_catalog_item_id')->nullable()->constrained('loot_catalog_items'));}
    public function down():void{Schema::table('activity_loot',fn(Blueprint $table)=>$table->dropConstrainedForeignId('loot_catalog_item_id'));Schema::dropIfExists('loot_catalog_items');}
};
