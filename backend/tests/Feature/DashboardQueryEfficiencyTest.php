<?php

namespace Tests\Feature;

use App\Enums\PlayerClass;
use App\Enums\UserRole;
use App\Models\Player;
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
}
