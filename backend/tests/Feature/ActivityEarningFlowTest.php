<?php

namespace Tests\Feature;

use App\Enums\PlayerClass;
use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\ActivityDefinition;
use App\Models\ActivityLoot;
use App\Models\Player;
use App\Models\TreasuryTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class ActivityEarningFlowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_mini_activity_distributes_loot_value_without_crediting_gold_balance(): void
    {
        $leader = $this->leader();
        $definition = ActivityDefinition::query()->create([
            'name' => 'Mini loot '.uniqid(),
            'type' => 'mini_activity',
            'is_active' => true,
        ]);
        $activity = Activity::query()->create([
            'activity_definition_id' => $definition->id,
            'occurred_at' => now(),
            'created_by' => $leader->id,
        ]);
        $players = collect(['First', 'Second', 'Third'])->map(fn (string $prefix) =>
            Player::query()->create([
                'nickname' => $prefix.substr(uniqid(), -6),
                'class' => PlayerClass::Melee,
                'is_active' => true,
            ])
        );
        $activity->players()->attach($players->pluck('id')->all(), ['created_at' => now()]);
        ActivityLoot::query()->create([
            'activity_id' => $activity->id,
            'item_name' => 'Оценённый дроп',
            'quantity' => 2,
            'unit_price' => 500,
            'created_by' => $leader->id,
        ]);
        $goldTransactionsBefore = TreasuryTransaction::query()->count();

        $this->actingAs($leader)
            ->postJson('/api/activities/'.$activity->id.'/complete')
            ->assertOk()
            ->assertJsonPath('gold_value', 1000);

        self::assertSame(3, $activity->earnings()->count());
        self::assertSame([333], $activity->earnings()->pluck('player_share')->unique()->values()->all());
        self::assertSame($goldTransactionsBefore, TreasuryTransaction::query()->count());
    }

    private function leader(): User
    {
        $suffix = str_replace('.', '', uniqid('', true));
        $user = User::query()->create([
            'discord_id' => $suffix,
            'discord_username' => 'leader-'.$suffix,
        ]);
        $user->forceFill(['role' => UserRole::GuildLeader, 'roles' => [UserRole::GuildLeader->value]])->save();
        $player = Player::query()->create([
            'nickname' => 'Leader'.substr($suffix, -8),
            'class' => PlayerClass::Melee,
            'is_active' => true,
        ]);
        $player->forceFill(['user_id' => $user->id])->save();

        return $user;
    }
}
