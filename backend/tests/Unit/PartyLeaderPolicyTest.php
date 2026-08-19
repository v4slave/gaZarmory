<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\GuildGroup;
use App\Models\Player;
use App\Models\User;
use App\Policies\GuildGroupPolicy;
use App\Policies\PlayerPolicy;
use PHPUnit\Framework\TestCase;

final class PartyLeaderPolicyTest extends TestCase
{
    public function test_party_leader_cannot_rename_or_delete_groups(): void
    {
        $user = $this->partyLeader(10);
        $ownGroup = (new GuildGroup())->forceFill(['id' => 10]);
        $otherGroup = (new GuildGroup())->forceFill(['id' => 11]);
        $policy = new GuildGroupPolicy();

        self::assertFalse($policy->update($user, $ownGroup));
        self::assertFalse($policy->delete($user, $ownGroup));
        self::assertFalse($policy->update($user, $otherGroup));
        self::assertFalse($policy->delete($user, $otherGroup));
        self::assertFalse($policy->create($user));
    }

    public function test_party_leader_can_move_solo_and_own_players_but_not_other_groups(): void
    {
        $user = $this->partyLeader(10);
        $policy = new PlayerPolicy();

        self::assertTrue($policy->move($user, (new Player())->forceFill(['group_id' => null])));
        self::assertTrue($policy->move($user, (new Player())->forceFill(['group_id' => 10])));
        self::assertFalse($policy->move($user, (new Player())->forceFill(['group_id' => 11])));
        self::assertFalse($policy->update($user, (new Player())->forceFill(['group_id' => 10])));
    }

    public function test_permissions_are_combined_when_user_has_multiple_roles(): void
    {
        $user = $this->partyLeader(10);
        $user->forceFill([
            'role' => UserRole::Developer,
            'roles' => [UserRole::Developer->value, UserRole::PartyLeader->value],
        ]);

        self::assertTrue($user->canManageGuild());
        self::assertTrue($user->hasRole(UserRole::PartyLeader));
        self::assertTrue((new GuildGroupPolicy())->update($user, (new GuildGroup())->forceFill(['id' => 11])));
    }

    public function test_developer_has_guild_management_and_administration_permissions(): void
    {
        $user = (new User())->forceFill([
            'role' => UserRole::Developer,
            'roles' => [UserRole::Developer->value],
        ]);

        self::assertTrue($user->canManageGuild());
        self::assertTrue($user->canAdministrate());
    }

    public function test_micro_guild_leader_has_management_but_not_restricted_financial_commands(): void
    {
        $user = (new User())->forceFill([
            'role' => UserRole::MicroGuildLeader,
            'roles' => [UserRole::MicroGuildLeader->value],
        ]);

        self::assertTrue($user->canManageGuild());
        self::assertFalse($user->canAdministrate());
        self::assertFalse($user->canCreateAuctions());
        self::assertFalse($user->canHandleTreasuryItems());
        self::assertFalse($user->canCreatePayouts());
    }

    private function partyLeader(int $groupId): User
    {
        $user = (new User())->forceFill(['role' => UserRole::PartyLeader, 'roles' => [UserRole::PartyLeader->value]]);
        $user->setRelation('player', (new Player())->forceFill(['group_id' => $groupId]));
        return $user;
    }
}
