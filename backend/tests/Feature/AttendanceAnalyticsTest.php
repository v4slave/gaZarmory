<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Enums\PlayerClass;
use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\ActivityDefinition;
use App\Models\GuildGroup;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class AttendanceAnalyticsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_report_shows_attended_out_of_available_and_streaks(): void
    {
        [$manager, $managerPlayer] = $this->userWithPlayer(UserRole::GuildLeader, false);
        $group = GuildGroup::query()->create(['name' => 'Analytics'.uniqid()]);
        $player = Player::query()->create(['nickname'=>'Analyticstest','class'=>PlayerClass::Mage,'is_active'=>true,'group_id'=>$group->id]);
        $player->forceFill(['created_at'=>now()->subDays(60)])->save();
        $definition = ActivityDefinition::query()->create(['name'=>'Prime'.uniqid(),'type'=>ActivityType::Prime,'is_active'=>true]);
        $first = Activity::query()->create(['activity_definition_id'=>$definition->id,'occurred_at'=>now()->subDays(10),'completed_at'=>now()->subDays(10),'created_by'=>$manager->id]);
        $second = Activity::query()->create(['activity_definition_id'=>$definition->id,'occurred_at'=>now()->subDays(2),'completed_at'=>now()->subDays(2),'created_by'=>$manager->id]);
        $first->players()->attach($player->id, ['created_at'=>now()->subDays(10)]);

        $this->actingAs($manager)->getJson('/api/attendance-analytics?period=30&group_id='.$group->id.'&player_id='.$player->id)
            ->assertOk()
            ->assertJsonPath('summary.attended', 1)
            ->assertJsonPath('summary.available', 2)
            ->assertJsonPath('players.0.attended', 1)
            ->assertJsonPath('players.0.available', 2)
            ->assertJsonPath('players.0.percentage', 50)
            ->assertJsonPath('players.0.absence_streak', 1)
            ->assertJsonPath('events.0.total', 2)
            ->assertJsonPath('groups.0.attended', 1);
    }

    public function test_csv_and_xlsx_exports_are_available_to_leaders(): void
    {
        [$manager] = $this->userWithPlayer(UserRole::GuildLeader, false);
        $this->actingAs($manager)->get('/api/attendance-analytics/export?period=7&format=csv')
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->actingAs($manager)->get('/api/attendance-analytics/export?period=7&format=xlsx')
            ->assertOk()->assertDownload();
    }

    public function test_member_cannot_open_attendance_analytics(): void
    {
        [$member] = $this->userWithPlayer(UserRole::Member, true);
        $this->actingAs($member)->getJson('/api/attendance-analytics')->assertForbidden();
    }

    private function userWithPlayer(UserRole $role, bool $active): array
    {
        $suffix = str_replace('.', '', uniqid('', true));
        $user = User::query()->create(['discord_id'=>$suffix,'discord_username'=>'analytics'.$suffix]);
        $user->forceFill(['roles'=>[$role->value],'role'=>$role])->save();
        $player = Player::query()->create(['nickname'=>'User'.substr($suffix,-8),'class'=>PlayerClass::Healer,'is_active'=>$active]);
        $player->forceFill(['user_id'=>$user->id])->save();
        return [$user,$player];
    }
}
