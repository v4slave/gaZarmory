<?php

namespace Tests\Feature;

use App\Enums\PlayerClass;
use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\ActivityDefinition;
use App\Models\Player;
use App\Models\PrimePlayerEarning;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class DashboardQueryEfficiencyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dashboard_builds_chart_without_per_day_queries(): void
    {
        $user = User::query()->create([
            'discord_id' => 'dashboard-query-'.uniqid(),
            'discord_username' => 'dashboard-query',
        ]);
        $user->forceFill(['role' => UserRole::Member, 'roles' => [UserRole::Member->value]])->save();
        Player::query()->create([
            'nickname' => 'DashboardQuery',
            'class' => PlayerClass::Melee,
            'is_active' => true,
        ])->forceFill(['user_id' => $user->id])->save();

        $queries = 0;
        DB::listen(static function () use (&$queries): void { $queries++; });

        $this->actingAs($user)->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonCount(14, 'treasury_dynamics');

        self::assertLessThanOrEqual(16, $queries, "Dashboard executed {$queries} SQL queries.");
    }

    public function test_dashboard_attendance_ignores_drafts_and_includes_legacy_earnings(): void
    {
        $user = User::query()->create(['discord_id'=>'dashboard-stats-'.uniqid(),'discord_username'=>'dashboard-stats']);
        $user->forceFill(['role'=>UserRole::Member,'roles'=>[UserRole::Member->value]])->save();
        $player = Player::query()->create(['nickname'=>'DashboardStats','class'=>PlayerClass::Melee,'is_active'=>true]);
        $player->forceFill(['user_id'=>$user->id])->save();
        $definition = ActivityDefinition::query()->create(['name'=>'Dashboard stats '.uniqid(),'type'=>'prime','is_active'=>true]);

        $draft = Activity::query()->create(['activity_definition_id'=>$definition->id,'occurred_at'=>now(),'created_by'=>$user->id]);
        $draft->players()->attach($player->id, ['created_at'=>now()]);
        $legacy = Activity::query()->create(['activity_definition_id'=>$definition->id,'occurred_at'=>now(),'created_by'=>$user->id]);
        $legacy->players()->attach($player->id, ['created_at'=>now()]);
        PrimePlayerEarning::query()->create([
            'activity_id'=>$legacy->id,'player_id'=>$player->id,'nickname_snapshot'=>$player->nickname,
            'prime_gold_value_snapshot'=>100,'participants_count_snapshot'=>1,'player_share'=>100,'status'=>'pending',
        ]);

        $response = $this->actingAs($user)->getJson('/api/dashboard')->assertOk();
        $row = collect($response->json('attendance_top'))->firstWhere('id', $player->id);
        self::assertNotNull($row);
        self::assertSame(1, $row['primes_count']);
    }
}
