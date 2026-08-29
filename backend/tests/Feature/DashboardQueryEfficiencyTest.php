<?php

namespace Tests\Feature;

use App\Enums\PlayerClass;
use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\ActivityDefinition;
use App\Models\Player;
use App\Models\PrimePlayerEarning;
use App\Models\User;
use Carbon\CarbonImmutable;
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

        $response = $this->actingAs($user)->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonCount(14, 'treasury_dynamics');

        self::assertLessThanOrEqual(17, $queries, "Dashboard executed {$queries} SQL queries.");

        $eventDates = collect($response->json('weekly_events'))
            ->map(fn (array $event): string => CarbonImmutable::parse($event['starts_at'])->toDateString())
            ->unique()->values();
        self::assertCount(7, $eventDates);
        self::assertSame(1, CarbonImmutable::parse($eventDates->first())->dayOfWeekIso);
        self::assertSame(7, CarbonImmutable::parse($eventDates->last())->dayOfWeekIso);
    }

    public function test_calendar_slots_link_to_activities_by_definition_date_and_time(): void
    {
        $user = User::query()->create([
            'discord_id' => 'dashboard-calendar-'.uniqid(),
            'discord_username' => 'dashboard-calendar',
        ]);
        $user->forceFill(['role' => UserRole::Member, 'roles' => [UserRole::Member->value]])->save();
        Player::query()->create([
            'nickname' => 'CalendarLinks',
            'class' => PlayerClass::Melee,
            'is_active' => true,
        ])->forceFill(['user_id' => $user->id])->save();

        $slots = collect($this->actingAs($user)->getJson('/api/dashboard')->assertOk()->json('weekly_events'));
        $definitions = $slots->pluck('name')->unique()->mapWithKeys(function (string $name): array {
            $definition = ActivityDefinition::query()->firstOrCreate(
                ['name' => $name],
                ['type' => 'prime', 'is_active' => true],
            );
            return [$name => $definition];
        });
        $activities = $slots->map(function (array $slot) use ($definitions, $user): Activity {
            return Activity::query()->create([
                'activity_definition_id' => $definitions[$slot['name']]->id,
                'occurred_at' => CarbonImmutable::parse($slot['starts_at']),
                'created_by' => $user->id,
            ]);
        });

        $linked = collect($this->actingAs($user)->getJson('/api/dashboard')->assertOk()->json('weekly_events'));
        self::assertCount($slots->count(), $linked->whereNotNull('activity_id'));
        $linked->values()->each(function (array $slot, int $index) use ($activities): void {
            self::assertSame($activities[$index]->id, $slot['activity_id']);
        });

        $cats = $linked->where('name', 'Кошка')->values();
        self::assertGreaterThan(1, $cats->count());
        self::assertNotSame($cats[0]['activity_id'], $cats[1]['activity_id']);
    }

    public function test_calendar_contains_agl_every_four_hours_each_day(): void
    {
        $user = User::query()->create([
            'discord_id' => 'dashboard-agl-'.uniqid(),
            'discord_username' => 'dashboard-agl',
        ]);
        $user->forceFill(['role' => UserRole::Member, 'roles' => [UserRole::Member->value]])->save();
        Player::query()->create([
            'nickname' => 'DashboardAgl',
            'class' => PlayerClass::Melee,
            'is_active' => true,
        ])->forceFill(['user_id' => $user->id])->save();

        $aglEvents = collect($this->actingAs($user)->getJson('/api/dashboard')->assertOk()->json('weekly_events'))
            ->where('name', 'АГЛ')
            ->groupBy(fn (array $event): string => CarbonImmutable::parse($event['starts_at'])->toDateString());

        self::assertCount(7, $aglEvents);
        $aglEvents->each(function ($events): void {
            self::assertNotNull($events->first()['definition_id']);
            self::assertSame(
                ['03:20', '07:20', '11:20', '15:20', '19:20', '23:20'],
                $events->map(fn (array $event): string => CarbonImmutable::parse($event['starts_at'])->format('H:i'))->values()->all(),
            );
        });
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
