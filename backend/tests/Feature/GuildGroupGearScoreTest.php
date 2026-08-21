<?php

namespace Tests\Feature;

use App\Enums\PlayerClass;
use App\Enums\UserRole;
use App\Models\GuildGroup;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class GuildGroupGearScoreTest extends TestCase
{
    use DatabaseTransactions;

    public function test_group_average_gear_score_reflects_profile_updates(): void
    {
        $group = GuildGroup::query()->create(['name' => 'Gear group '.uniqid()]);
        $user = User::query()->create([
            'discord_id' => uniqid('gear-', true),
            'discord_username' => 'gear-user-'.uniqid(),
        ]);
        $user->forceFill(['role' => UserRole::Member, 'roles' => [UserRole::Member->value]])->save();
        $player = Player::query()->create([
            'group_id' => $group->id,
            'nickname' => 'Gear'.substr(uniqid(), -8),
            'class' => PlayerClass::Melee,
            'is_active' => true,
            'gear_score' => 10000,
        ]);
        $player->forceFill(['user_id' => $user->id])->save();

        $second = Player::query()->create([
            'group_id' => $group->id,
            'nickname' => 'Gear'.substr(uniqid(), -8),
            'class' => PlayerClass::Mage,
            'is_active' => true,
            'gear_score' => 20000,
        ]);

        $this->assertGroupAverage($user, $group, 15000);

        $profile = ['gear_score' => 30000];
        foreach (['has_ship','has_tank','has_fuchsias','has_clouds','has_machaon','has_tare','has_deer','has_invulnerable_pet','has_shield_swap','has_flippers'] as $field) {
            $profile[$field] = false;
        }
        $this->actingAs($user)->patchJson('/api/me/player/profile', $profile)->assertOk();

        $this->assertGroupAverage($user, $group, 25000);
    }

    private function assertGroupAverage(User $user, GuildGroup $group, int $expected): void
    {
        $response = $this->actingAs($user)->getJson('/api/groups')->assertOk();
        $row = collect($response->json())->firstWhere('id', $group->id);
        self::assertNotNull($row);
        self::assertSame($expected, $row['average_gear_score']);
    }
}
