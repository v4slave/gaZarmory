<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private const PRIME = ['Кракен','Т2 Кракен','Ксанатос','Месания','Кошка','Анталлон','Калеиль','Калидис','Левиафан','Т2 Левиафан','Авиара','АГЛ','Т2 АГЛ','Морф'];
    private const MINI = ['Марля','Т2 Марля','Жук'];

    public function up(): void
    {
        $now = now();
        $rows = [];
        foreach (self::PRIME as $name) $rows[] = ['name'=>$name,'type'=>'prime','is_active'=>true,'created_at'=>$now,'updated_at'=>$now];
        foreach (self::MINI as $name) $rows[] = ['name'=>$name,'type'=>'mini_activity','is_active'=>true,'created_at'=>$now,'updated_at'=>$now];
        DB::table('activity_definitions')->upsert($rows, ['name','type'], ['is_active','updated_at']);
        DB::table('activity_definitions')->where('type','activity')->update(['is_active'=>false,'updated_at'=>$now]);
    }

    public function down(): void
    {
        DB::table('activity_definitions')->whereIn('name',array_merge(self::PRIME,self::MINI))->update(['is_active'=>false,'updated_at'=>now()]);
    }
};
