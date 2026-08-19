<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $leaders = DB::table('users')
            ->whereJsonContains('roles', 'guild_leader')
            ->orderBy('id')
            ->get(['id', 'roles']);

        foreach ($leaders->skip(1) as $leader) {
            $roles = is_string($leader->roles) ? json_decode($leader->roles, true) : (array) $leader->roles;
            $roles = array_values(array_filter($roles, fn (string $role): bool => $role !== 'guild_leader'));
            if ($roles === []) $roles = ['member'];
            $primary = collect(['micro_guild_leader', 'developer', 'party_leader', 'member'])
                ->first(fn (string $role): bool => in_array($role, $roles, true)) ?? 'member';
            DB::table('users')->where('id', $leader->id)->update([
                'roles' => json_encode($roles),
                'role' => $primary,
                'updated_at' => now(),
            ]);
        }

        DB::statement("CREATE UNIQUE INDEX users_single_guild_leader ON users ((1)) WHERE roles @> '[\"guild_leader\"]'::jsonb");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_single_guild_leader');
    }
};
