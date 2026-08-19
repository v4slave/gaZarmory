<?php

namespace Tests\Feature;

use App\Enums\PlayerClass;
use App\Enums\UserRole;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class AdminUserLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_guild_leader_can_restore_an_inactive_player(): void
    {
        $leader = $this->userWithRoles([UserRole::GuildLeader->value]);
        $player = Player::query()->create([
            'nickname' => 'Восстановимый',
            'class' => PlayerClass::Melee,
            'is_active' => false,
        ]);

        $this->actingAs($leader)->postJson("/api/players/{$player->id}/activate")
            ->assertOk()
            ->assertJsonPath('is_active', true);

        $this->assertTrue($player->refresh()->is_active);
    }

    public function test_administrator_can_delete_wrong_discord_account_without_deleting_player(): void
    {
        $leader = $this->userWithRoles([UserRole::GuildLeader->value]);
        $wrongUser = $this->userWithRoles([UserRole::Member->value]);
        $player = Player::query()->create([
            'user_id' => $wrongUser->id,
            'nickname' => 'Сохранённый',
            'class' => PlayerClass::Healer,
            'is_active' => true,
        ]);

        $this->actingAs($leader)->deleteJson("/api/admin/users/{$wrongUser->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $wrongUser->id]);
        $this->assertDatabaseHas('players', ['id' => $player->id, 'user_id' => null]);
    }

    public function test_administrator_cannot_delete_self(): void
    {
        $leader = $this->userWithRoles([UserRole::GuildLeader->value]);

        $this->actingAs($leader)->deleteJson("/api/admin/users/{$leader->id}")
            ->assertUnprocessable();
    }

    public function test_guild_leader_role_is_transferred_atomically(): void
    {
        $leader = $this->userWithRoles([UserRole::GuildLeader->value, UserRole::Member->value]);
        $successor = $this->userWithRoles([UserRole::PartyLeader->value, UserRole::Member->value]);

        $this->actingAs($leader)->patchJson("/api/admin/users/{$successor->id}/roles", [
            'roles' => [UserRole::GuildLeader->value, UserRole::PartyLeader->value, UserRole::Member->value],
            'updated_at' => $successor->updated_at->toISOString(),
        ])->assertOk();

        self::assertFalse($leader->fresh()->hasRole(UserRole::GuildLeader));
        self::assertTrue($leader->fresh()->hasRole(UserRole::Member));
        self::assertTrue($successor->fresh()->hasRole(UserRole::GuildLeader));
        self::assertSame(1, User::query()->whereJsonContains('roles', UserRole::GuildLeader->value)->count());
    }

    public function test_developer_role_cannot_be_assigned_through_admin_api(): void
    {
        $leader = $this->userWithRoles([UserRole::GuildLeader->value]);
        $member = $this->userWithRoles([UserRole::Member->value]);

        $this->actingAs($leader)->patchJson("/api/admin/users/{$member->id}/roles", [
            'roles' => [UserRole::Developer->value, UserRole::Member->value],
        ])->assertUnprocessable();

        self::assertFalse($member->fresh()->hasRole(UserRole::Developer));
    }

    private function userWithRoles(array $roles): User
    {
        $suffix = str_replace('.', '', uniqid('', true));
        $user = User::query()->create([
            'discord_id' => $suffix,
            'discord_username' => 'lifecycle-'.$suffix,
        ]);
        $user->forceFill(['roles' => $roles, 'role' => User::primaryRoleFor($roles)])->save();
        Player::query()->create(['nickname' => 'Life'.$user->id, 'class' => PlayerClass::Melee, 'is_active' => false])
            ->forceFill(['user_id' => $user->id])->save();
        return $user;
    }
}
