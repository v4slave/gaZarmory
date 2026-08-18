<?php

namespace Tests\Feature;

use App\Enums\PlayerClass;
use App\Enums\ActivityType;
use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\ActivityDefinition;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PlayerPermanentDeleteTest extends TestCase
{
    use DatabaseTransactions;

    public function test_guild_leader_can_permanently_delete_unlinked_player_without_history(): void
    {
        $leader = $this->leader();
        $player = Player::query()->create([
            'nickname' => 'БезИстории',
            'class' => PlayerClass::Melee,
            'is_active' => true,
        ]);

        $this->actingAs($leader)->deleteJson("/api/players/{$player->id}/permanent")
            ->assertNoContent();

        $this->assertDatabaseMissing('players', ['id' => $player->id]);
    }

    public function test_linked_player_cannot_be_permanently_deleted(): void
    {
        $leader = $this->leader();

        $this->actingAs($leader)->deleteJson("/api/players/{$leader->player->id}/permanent")
            ->assertUnprocessable();

        $this->assertDatabaseHas('players', ['id' => $leader->player->id]);
    }

    public function test_player_with_history_cannot_be_permanently_deleted(): void
    {
        $leader = $this->leader();
        $player = Player::query()->create([
            'nickname' => 'СИсторией',
            'class' => PlayerClass::Healer,
            'is_active' => true,
        ]);
        $definition = ActivityDefinition::query()->create([
            'name' => 'История удаления '.uniqid(),
            'type' => ActivityType::Prime,
            'is_active' => true,
        ]);
        $activity = Activity::query()->create([
            'activity_definition_id' => $definition->id,
            'occurred_at' => now(),
            'gold_value' => 0,
            'created_by' => $leader->id,
        ]);
        DB::table('activity_players')->insert([
            'activity_id' => $activity->id,
            'player_id' => $player->id,
            'created_at' => now(),
        ]);

        $this->actingAs($leader)->deleteJson("/api/players/{$player->id}/permanent")
            ->assertUnprocessable();

        $this->assertDatabaseHas('players', ['id' => $player->id]);
    }

    private function leader(): User
    {
        $suffix = str_replace('.', '', uniqid('', true));
        $user = User::query()->create([
            'discord_id' => $suffix,
            'discord_username' => 'delete-'.$suffix,
        ]);
        $user->forceFill([
            'roles' => [UserRole::GuildLeader->value],
            'role' => UserRole::GuildLeader->value,
        ])->save();
        Player::query()->create([
            'nickname' => 'Delete'.$user->id,
            'class' => PlayerClass::Melee,
            'is_active' => true,
        ])->forceFill(['user_id' => $user->id])->save();

        return $user->refresh()->load('player');
    }
}
