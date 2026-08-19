<?php

namespace Tests\Feature;

use App\Enums\PlayerClass;
use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\ActivityDefinition;
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

    public function test_search_treats_sql_wildcards_and_injection_text_as_literals(): void
    {
        $user = User::query()->create([
            'discord_id' => str_replace('.', '', uniqid('', true)),
            'discord_username' => 'safe-search',
        ]);
        $user->forceFill(['role' => UserRole::Member, 'roles' => [UserRole::Member->value]])->save();
        Player::query()->create(['nickname' => 'SearchUser'.$user->id, 'class' => PlayerClass::Melee, 'is_active' => true])
            ->forceFill(['user_id' => $user->id])->save();
        $literal = Player::query()->create(['nickname' => 'Wildcard%Needle', 'class' => PlayerClass::Mage, 'is_active' => true]);
        Player::query()->create(['nickname' => 'WildcardXNeedle', 'class' => PlayerClass::Mage, 'is_active' => true]);

        $this->actingAs($user)
            ->getJson('/api/players?'.http_build_query(['search' => '%']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $literal->id);

        $this->actingAs($user)
            ->getJson('/api/players?'.http_build_query(['search' => "' OR 1=1 --"]))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_prime_count_excludes_drafts(): void
    {
        $user = User::query()->create(['discord_id'=>'player-stats-'.uniqid(),'discord_username'=>'player-stats']);
        $user->forceFill(['role'=>UserRole::Member,'roles'=>[UserRole::Member->value]])->save();
        $player = Player::query()->create(['nickname'=>'PlayerStats','class'=>PlayerClass::Mage,'is_active'=>true]);
        $player->forceFill(['user_id'=>$user->id])->save();
        $definition = ActivityDefinition::query()->create(['name'=>'Player stats '.uniqid(),'type'=>'prime','is_active'=>true]);
        $draft = Activity::query()->create(['activity_definition_id'=>$definition->id,'occurred_at'=>now(),'created_by'=>$user->id]);
        $draft->players()->attach($player->id, ['created_at'=>now()]);
        $completed = Activity::query()->create(['activity_definition_id'=>$definition->id,'occurred_at'=>now(),'completed_at'=>now(),'created_by'=>$user->id]);
        $completed->players()->attach($player->id, ['created_at'=>now()]);

        $this->actingAs($user)->getJson('/api/players?search=PlayerStats')
            ->assertOk()
            ->assertJsonPath('data.0.id', $player->id)
            ->assertJsonPath('data.0.primes_count', 1);
    }
}
