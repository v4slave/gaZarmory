<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\PlayerClass;
use App\Models\AuditLog;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class AuditLogAccessTest extends TestCase
{
    use DatabaseTransactions;

    public function test_guild_leader_can_view_audit_log(): void
    {
        $leader = $this->userWithRoles([UserRole::GuildLeader->value]);
        AuditLog::query()->create([
            'user_id' => $leader->id,
            'action' => 'test.action',
            'entity_type' => User::class,
            'entity_id' => $leader->id,
            'new_values' => ['test' => true],
            'ip_address' => '127.0.0.1',
        ]);

        $this->actingAs($leader)->getJson('/api/admin/audit-logs')
            ->assertOk()
            ->assertJsonPath('logs.data.0.action', 'test.action');
    }

    public function test_developer_can_view_audit_log(): void
    {
        $developer = $this->userWithRoles([UserRole::Developer->value]);

        $this->actingAs($developer)->getJson('/api/admin/audit-logs')->assertOk();
        $this->actingAs($developer)->getJson('/api/admin/users')->assertOk();
    }

    public function test_non_leader_cannot_view_audit_log(): void
    {
        $member = $this->userWithRoles([UserRole::Member->value]);
        $this->actingAs($member)->getJson('/api/admin/audit-logs')->assertForbidden();
    }

    private function userWithRoles(array $roles): User
    {
        $suffix = str_replace('.', '', uniqid('', true));
        $user = User::query()->create([
            'discord_id' => $suffix,
            'discord_username' => 'audit-'.$suffix,
        ]);
        $user->forceFill(['roles' => $roles, 'role' => User::primaryRoleFor($roles)])->save();
        Player::query()->create(['nickname' => 'Audit'.$user->id, 'class' => PlayerClass::Melee, 'is_active' => false])
            ->forceFill(['user_id' => $user->id])->save();
        return $user;
    }
}
