<?php

namespace Tests\Feature;

use App\Enums\PlayerClass;
use App\Enums\UserRole;
use App\Models\GuildGroup;
use App\Models\Player;
use App\Models\PlayerGearScoreHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class RosterReadinessTest extends TestCase
{
    use DatabaseTransactions;

    public function test_manager_can_filter_players_and_see_period_deltas(): void
    {
        $manager = $this->user(UserRole::GuildLeader, 'manager');
        $group = GuildGroup::query()->create(['name' => 'Ready'.uniqid()]);
        $player = Player::query()->create([
            'nickname' => 'Readytest', 'class' => PlayerClass::Tank, 'is_active' => true,
            'group_id' => $group->id, 'gear_score' => 26000, 'has_shield_swap' => false,
        ]);
        PlayerGearScoreHistory::query()->create(['player_id' => $player->id, 'gear_score' => 24000, 'recorded_at' => now()->subDays(35)]);

        $this->actingAs($manager)->getJson('/api/roster-readiness?group_id='.$group->id.'&min_gear_score=25000&missing_asset=has_shield_swap')
            ->assertOk()
            ->assertJsonCount(1, 'players')
            ->assertJsonPath('players.0.id', $player->id)
            ->assertJsonPath('players.0.gear_score_month_delta', 2000);
    }

    public function test_party_leader_only_sees_own_group_and_member_is_forbidden(): void
    {
        $own = GuildGroup::query()->create(['name' => 'Own'.uniqid()]);
        $other = GuildGroup::query()->create(['name' => 'Other'.uniqid()]);
        $leader = $this->user(UserRole::PartyLeader, 'leader');
        $leader->player->update(['is_active' => true, 'group_id' => $own->id]);
        Player::query()->create(['nickname' => 'Ownmember', 'class' => PlayerClass::Mage, 'is_active' => true, 'group_id' => $own->id]);
        Player::query()->create(['nickname' => 'Outsider', 'class' => PlayerClass::Archer, 'is_active' => true, 'group_id' => $other->id]);

        $this->actingAs($leader)->getJson('/api/roster-readiness?group_id='.$other->id)
            ->assertOk()->assertJsonCount(2, 'players')->assertJsonCount(1, 'groups');

        $member = $this->user(UserRole::Member, 'member');
        $this->actingAs($member)->getJson('/api/roster-readiness')->assertForbidden();
    }

    private function user(UserRole $role, string $prefix): User
    {
        $suffix = str_replace('.', '', uniqid('', true));
        $user = User::query()->create(['discord_id' => $suffix, 'discord_username' => $prefix.$suffix]);
        $user->forceFill(['roles' => [$role->value], 'role' => $role])->save();
        $player = Player::query()->create(['nickname' => ucfirst($prefix).substr($suffix, -8), 'class' => PlayerClass::Healer, 'is_active' => false]);
        $player->forceFill(['user_id' => $user->id])->save();
        $user->setRelation('player', $player);
        return $user;
    }
}
