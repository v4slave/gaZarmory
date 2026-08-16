<?php

namespace Tests\Feature;

use App\Enums\PlayerClass;
use App\Enums\UserRole;
use App\Models\GuildGroup;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class PlayerFilterTest extends TestCase
{
    use DatabaseTransactions;

    public function test_solo_and_active_query_strings_are_accepted_as_booleans(): void
    {
        $user = User::query()->create([
            'discord_id' => str_replace('.', '', uniqid('', true)),
            'discord_username' => 'filter-test',
        ]);
        $user->forceFill(['role' => UserRole::Member, 'roles' => [UserRole::Member->value]])->save();
        Player::query()->create(['nickname' => 'Filter'.$user->id, 'class' => PlayerClass::Melee, 'is_active' => false])
            ->forceFill(['user_id' => $user->id])->save();
        $group = GuildGroup::query()->create(['name' => 'Filter group '.uniqid()]);
        $solo = Player::query()->create(['nickname' => 'Solo '.uniqid(), 'class' => PlayerClass::Melee, 'is_active' => true]);
        Player::query()->create(['nickname' => 'Grouped '.uniqid(), 'class' => PlayerClass::Melee, 'group_id' => $group->id, 'is_active' => true]);

        $this->actingAs($user)
            ->getJson('/api/players?solo=true&active=true')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $solo->id);
    }
}
