<?php

namespace Tests\Feature;

use App\Enums\PlayerClass;
use App\Enums\UserRole;
use App\Models\GuildGroup;
use App\Models\Player;
use App\Models\TreasuryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class RoleRestrictionsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_micro_guild_leader_cannot_create_auction_sell_issue_or_create_payout(): void
    {
        $micro = $this->user(UserRole::MicroGuildLeader);
        $item = TreasuryItem::query()->create(['item_name' => 'Restricted '.uniqid(), 'quantity' => 2, 'reserved_quantity' => 0, 'unit_value' => 100]);

        $this->actingAs($micro)->postJson('/api/auctions', [])->assertForbidden();
        $this->actingAs($micro)->postJson('/api/treasury/items/'.$item->id.'/sell', [])->assertForbidden();
        $this->actingAs($micro)->postJson('/api/treasury/items/'.$item->id.'/issue', [])->assertForbidden();
        $this->actingAs($micro)->postJson('/api/payouts', [])->assertForbidden();
    }

    public function test_micro_guild_leader_keeps_guild_management_but_loses_administration_access(): void
    {
        $micro = $this->user(UserRole::MicroGuildLeader);
        $member = $this->user(UserRole::Member);

        $this->actingAs($micro)->getJson('/api/admin/users')->assertForbidden();
        $this->actingAs($micro)->postJson('/api/activities', [])->assertUnprocessable();
        $this->actingAs($micro)->patchJson('/api/admin/users/'.$member->id.'/roles', ['roles' => [UserRole::Developer->value]])->assertForbidden();
        self::assertTrue($member->fresh()->hasRole(UserRole::Member));
    }

    public function test_micro_guild_leader_has_member_permissions_in_economy(): void
    {
        $micro = $this->user(UserRole::MicroGuildLeader);

        $this->actingAs($micro)->postJson('/api/treasury/transactions', [
            'operation' => 'expense', 'amount' => 1, 'description' => 'Forbidden',
        ])->assertForbidden();
        $this->actingAs($micro)->getJson('/api/payouts-preview')->assertForbidden();
        $this->actingAs($micro)->postJson('/api/auctions', [])->assertForbidden();
    }

    public function test_party_leader_can_move_composition_but_cannot_rename_or_delete_group(): void
    {
        $partyLeader = $this->user(UserRole::PartyLeader);
        $group = GuildGroup::query()->create(['name' => 'Party '.uniqid()]);
        $partyLeader->player->update(['group_id' => $group->id]);
        $solo = Player::query()->create(['nickname' => 'Solo'.substr(uniqid(), -6), 'class' => PlayerClass::Archer, 'is_active' => true]);

        $this->actingAs($partyLeader)->putJson('/api/players/'.$solo->id.'/group', ['group_id' => $group->id])->assertOk();
        $this->actingAs($partyLeader)->putJson('/api/groups/'.$group->id, ['name' => 'Forbidden'])->assertForbidden();
        $this->actingAs($partyLeader)->deleteJson('/api/groups/'.$group->id)->assertForbidden();
    }

    private function user(UserRole $role): User
    {
        $suffix = str_replace('.', '', uniqid('', true));
        $user = User::query()->create(['discord_id' => $suffix, 'discord_username' => 'role-'.$suffix]);
        $user->forceFill(['role' => $role, 'roles' => [$role->value]])->save();
        $player = Player::query()->create(['nickname' => 'Role'.substr($suffix, -8), 'class' => PlayerClass::Melee, 'is_active' => true]);
        $player->forceFill(['user_id' => $user->id])->save();
        $user->setRelation('player', $player);
        return $user;
    }
}
