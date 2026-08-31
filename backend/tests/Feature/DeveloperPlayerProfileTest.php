<?php

namespace Tests\Feature;

use App\Enums\PlayerClass;
use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\ActivityDefinition;
use App\Models\Player;
use App\Models\PlayerGearScoreHistory;
use App\Models\PrimePlayerEarning;
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

    public function test_profile_summary_excludes_history_collections(): void
    {
        $developer = $this->userWithRole(UserRole::Developer, 'summary');
        $player = Player::query()->create([
            'nickname' => 'Summaryprofile',
            'class' => PlayerClass::Mage,
            'is_active' => true,
        ]);

        $this->actingAs($developer)
            ->getJson('/api/players/'.$player->id)
            ->assertOk()
            ->assertJsonMissingPath('activities')
            ->assertJsonMissingPath('earnings_history')
            ->assertJsonStructure(['id', 'nickname', 'statistics']);
    }

    public function test_profile_histories_are_returned_by_paginated_endpoints(): void
    {
        $developer = $this->userWithRole(UserRole::Developer, 'history');
        $player = Player::query()->create([
            'nickname' => 'Historyprofile',
            'class' => PlayerClass::Archer,
            'is_active' => true,
        ]);
        $definition = ActivityDefinition::query()->create([
            'name' => 'Profile history prime',
            'type' => 'prime',
            'is_active' => true,
        ]);

        foreach ([1, 2, 3] as $index) {
            $activity = Activity::query()->create([
                'activity_definition_id' => $definition->id,
                'occurred_at' => now()->subDays(4 - $index),
                'gold_value' => 300,
                'created_by' => $developer->id,
            ]);
            $activity->players()->attach($player->id, ['created_at' => now()]);
            PrimePlayerEarning::query()->create([
                'activity_id' => $activity->id,
                'player_id' => $player->id,
                'nickname_snapshot' => $player->nickname,
                'prime_gold_value_snapshot' => 300,
                'participants_count_snapshot' => 1,
                'player_share' => 100 * $index,
                'status' => 'pending',
            ]);
            PlayerGearScoreHistory::query()->create([
                'player_id' => $player->id,
                'gear_score' => 10000 + $index,
                'recorded_at' => now()->subDays(4 - $index),
            ]);
        }

        $this->actingAs($developer)
            ->getJson('/api/players/'.$player->id.'/activities?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('total', 3)
            ->assertJsonPath('last_page', 2)
            ->assertJsonPath('data.0.definition.name', 'Profile history prime');

        $this->actingAs($developer)
            ->getJson('/api/players/'.$player->id.'/earnings?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('total', 3)
            ->assertJsonPath('data.0.player_share', 300);

        $this->actingAs($developer)
            ->getJson('/api/players/'.$player->id.'/gear-score-history?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('total', 3)
            ->assertJsonPath('data.0.gear_score', 10003);
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
