<?php

namespace Tests\Feature;

use App\Enums\PlayerClass;
use App\Enums\UserRole;
use App\Models\GuildGroup;
use App\Models\PartySquad;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class PartySquadTest extends TestCase
{
    use DatabaseTransactions;

    public function test_party_leader_can_manage_own_squads_and_not_another_party(): void
    {
        $own = GuildGroup::query()->create(['name' => 'Squads own '.uniqid()]);
        $other = GuildGroup::query()->create(['name' => 'Squads other '.uniqid()]);
        [$leader, $leaderPlayer] = $this->userWithPlayer(UserRole::PartyLeader, $own);
        $member = Player::query()->create(['nickname'=>'Squadmate'.uniqid(),'class'=>PlayerClass::Mage,'is_active'=>true,'group_id'=>$own->id,'gear_score'=>25000]);

        $response = $this->actingAs($leader)->postJson('/api/groups/'.$own->id.'/squads', ['name' => 'Первая'])->assertCreated();
        $squadId = $response->json('id');
        $this->putJson('/api/groups/'.$own->id.'/squads/'.$squadId.'/players', ['player_id'=>$member->id])->assertNoContent();
        $this->getJson('/api/groups/'.$own->id.'/squads')->assertOk()
            ->assertJsonPath('group.id', $own->id)->assertJsonPath('squads.0.player_ids.0', $member->id)
            ->assertJsonPath('players.1.gear_score', 25000);
        $this->getJson('/api/groups/'.$other->id.'/squads')->assertOk()->assertJsonPath('can_edit', false);
    }

    public function test_squad_is_limited_to_five_members(): void
    {
        $group = GuildGroup::query()->create(['name' => 'Squads limit '.uniqid()]);
        [$leader] = $this->userWithPlayer(UserRole::PartyLeader, $group);
        $squad = PartySquad::query()->create(['group_id'=>$group->id,'name'=>'Пять','position'=>1]);
        $players = collect(range(1, 6))->map(fn ($number) => Player::query()->create(['nickname'=>'Five'.$number.uniqid(),'class'=>PlayerClass::Healer,'is_active'=>true,'group_id'=>$group->id]));
        foreach ($players->take(5) as $player) $squad->players()->attach($player->id);

        $this->actingAs($leader)->putJson('/api/groups/'.$group->id.'/squads/'.$squad->id.'/players', ['player_id'=>$players->last()->id])
            ->assertUnprocessable()->assertJsonValidationErrors('player_id');
    }

    public function test_regular_member_can_view_squads_but_cannot_edit_them(): void
    {
        $group = GuildGroup::query()->create(['name' => 'Squads denied '.uniqid()]);
        [$member] = $this->userWithPlayer(UserRole::Member, $group);
        $this->actingAs($member)->getJson('/api/groups/'.$group->id.'/squads')
            ->assertOk()->assertJsonPath('can_edit', false);
        $this->postJson('/api/groups/'.$group->id.'/squads', ['name'=>'Нельзя'])->assertForbidden();
    }

    private function userWithPlayer(UserRole $role, GuildGroup $group): array
    {
        $suffix = str_replace('.', '', uniqid('', true));
        $user = User::query()->create(['discord_id'=>$suffix,'discord_username'=>'squads'.$suffix]);
        $user->forceFill(['roles'=>[$role->value],'role'=>$role])->save();
        $player = Player::query()->create(['nickname'=>'Leader'.substr($suffix,-8),'class'=>PlayerClass::Tank,'is_active'=>true,'group_id'=>$group->id]);
        $player->forceFill(['user_id'=>$user->id])->save();
        return [$user,$player];
    }
}
