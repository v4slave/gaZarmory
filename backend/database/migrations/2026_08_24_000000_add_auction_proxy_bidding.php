<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{Schema::table('auctions',function(Blueprint $t){$t->unsignedSmallInteger('extension_minutes')->default(3);$t->unsignedInteger('extensions_count')->default(0);});Schema::table('auction_bids',fn(Blueprint $t)=>$t->boolean('is_auto')->default(false));Schema::create('auction_auto_bids',function(Blueprint $t){$t->id();$t->foreignId('auction_id')->constrained()->cascadeOnDelete();$t->foreignId('player_id')->constrained()->cascadeOnDelete();$t->unsignedBigInteger('max_amount');$t->timestampsTz();$t->unique(['auction_id','player_id']);$t->index(['auction_id','max_amount']);});}
 public function down():void{Schema::dropIfExists('auction_auto_bids');Schema::table('auction_bids',fn(Blueprint $t)=>$t->dropColumn('is_auto'));Schema::table('auctions',fn(Blueprint $t)=>$t->dropColumn(['extension_minutes','extensions_count']));}
};
