<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();

        DB::table('activity_definitions')->updateOrInsert(
            ['name' => 'Марля', 'type' => 'prime'],
            [
                'is_active' => true,
                'icon_path' => 'activity-definitions/rangora.png',
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    public function down(): void
    {
        DB::table('activity_definitions')
            ->where('name', 'Марля')
            ->where('type', 'prime')
            ->update(['is_active' => false, 'updated_at' => now()]);
    }
};
