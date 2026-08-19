<?php
use Illuminate\Database\Migrations\Migration;use Illuminate\Database\Schema\Blueprint;use Illuminate\Support\Facades\Schema;
return new class extends Migration {public function up():void{Schema::table('notifications',function(Blueprint $t){$t->string('dedupe_key',180)->nullable();$t->unique(['user_id','dedupe_key']);$t->index(['user_id','read_at','created_at']);});}public function down():void{Schema::table('notifications',function(Blueprint $t){$t->dropIndex(['user_id','read_at','created_at']);$t->dropUnique(['user_id','dedupe_key']);$t->dropColumn('dedupe_key');});}};
