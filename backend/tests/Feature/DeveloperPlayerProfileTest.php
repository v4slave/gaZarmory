<?php

namespace Tests\Feature;

use App\Enums\PlayerClass;
use App\Enums\UserRole;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class DeveloperPlayerProfileTest extends TestCase
{
    use DatabaseTransactions;

    public function test_developer_can_update_another_players_profile(): void
    {
        $developer = $this->userWithRole(UserRole::Developer, 'developer');
        $player = Player::query()->create([
            'nickname' => 'Beforeprofile',
            'class' => PlayerClass::Melee,
            'gear_score' => 10000,
            'is_active' => true,
        ]);

        $this->actingAs($developer)
            ->patchJson('/api/players/'.$player->id.'/profile', $this->payload())
            ->assertOk()
            ->assertJsonPath('nickname', 'Afterprofile')
            ->assertJsonPath('class', PlayerClass::Healer->value)
            ->assertJsonPath('gear_score', 15000)
            ->assertJsonPath('previous_gear_score', 10000)
            ->assertJsonPath('has_ship', true);

        $this->assertDatabaseHas('player_gear_score_history', [
            'player_id' => $player->id,
            'gear_score' => 15000,
        ]);
    }

    public function test_member_cannot_update_another_players_profile(): void
    {
        $member = $this->userWithRole(UserRole::Member, 'member');
        $player = Player::query()->create([
            'nickname' => 'Lockedprofile',
            'class' => PlayerClass::Melee,
            'is_active' => true,
        ]);

        $this->actingAs($member)
            ->patchJson('/api/players/'.$player->id.'/profile', $this->payload())
            ->assertForbidden();
    }

    private function userWithRole(UserRole $role, string $prefix): User
    {
        $suffix = str_replace('.', '', uniqid('', true));
        $user = User::query()->create([
            'discord_id' => $prefix.$suffix,
            'discord_username' => $prefix.'-'.$suffix,
        ]);
        $user->forceFill(['roles' => [$role->value], 'role' => $role])->save();

        return $user;
    }

    private function payload(): array
    {
        return [
            'nickname' => 'Afterprofile',
            'class' => PlayerClass::Healer->value,
            'gear_score' => 15000,
            'has_ship' => true,
            'has_tank' => false,
            'has_fuchsias' => true,
            'has_clouds' => false,
            'has_machaon' => true,
            'has_tare' => false,
            'has_deer' => true,
            'has_invulnerable_pet' => false,
            'has_shield_swap' => true,
            'has_flippers' => false,
        ];
    }
}
