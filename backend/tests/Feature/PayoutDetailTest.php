<?php

namespace Tests\Feature;

use App\Enums\PlayerClass;
use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\ActivityDefinition;
use App\Models\Payout;
use App\Models\Player;
use App\Models\PrimePlayerEarning;
use App\Models\TreasuryTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class PayoutDetailTest extends TestCase
{
    use DatabaseTransactions;

    public function test_manager_and_included_member_can_view_payout_but_other_member_cannot(): void
    {
        $leader = $this->user(UserRole::GuildLeader);
        [$includedUser, $includedPlayer] = $this->memberWithPlayer('Included');
        [$otherUser] = $this->memberWithPlayer('Other');
        $payout = Payout::query()->create(['period_from'=>'2026-08-01','period_to'=>'2026-08-16','status'=>'calculated','total_amount'=>150,'created_by'=>$leader->id]);
        $payout->players()->create(['player_id'=>$includedPlayer->id,'nickname_snapshot'=>$includedPlayer->nickname,'prime_attendance_percentage_snapshot'=>50,'primes_count'=>1,'mini_activities_count'=>2,'amount'=>150,'status'=>'pending']);

        $this->actingAs($leader)->getJson('/api/payouts/'.$payout->id)->assertOk()->assertJsonPath('players.0.nickname_snapshot',$includedPlayer->nickname);
        $this->actingAs($includedUser)->getJson('/api/payouts/'.$payout->id)->assertOk();
        $this->actingAs($otherUser)->getJson('/api/payouts/'.$payout->id)->assertForbidden();
    }

    public function test_paid_payout_cannot_be_completed_twice(): void
    {
        $leader = $this->user(UserRole::GuildLeader);
        $payout = Payout::query()->create(['period_from'=>'2026-08-01','period_to'=>'2026-08-16','status'=>'paid','total_amount'=>0,'paid_at'=>now(),'created_by'=>$leader->id]);

        $this->actingAs($leader)->postJson('/api/payouts/'.$payout->id.'/complete')->assertUnprocessable();
    }

    public function test_manager_can_adjust_calculated_player_amount_and_total(): void
    {
        $leader=$this->user(UserRole::GuildLeader);
        [, $player]=$this->memberWithPlayer('Adjusted');
        $payout=Payout::query()->create(['period_from'=>'2026-08-01','period_to'=>'2026-08-16','status'=>'calculated','total_amount'=>100,'created_by'=>$leader->id]);
        $row=$payout->players()->create(['player_id'=>$player->id,'nickname_snapshot'=>$player->nickname,'prime_attendance_percentage_snapshot'=>100,'primes_count'=>1,'mini_activities_count'=>0,'amount'=>100,'status'=>'pending']);

        $this->actingAs($leader)->patchJson('/api/payouts/'.$payout->id.'/players/'.$row->id,['amount'=>275])
            ->assertOk()->assertJsonPath('total_amount',275);
        self::assertSame(275,$payout->fresh()->total_amount);
    }

    public function test_empty_draft_can_be_deleted(): void
    {
        $developer=$this->user(UserRole::Developer);
        $payout=Payout::query()->create(['period_from'=>'2026-08-01','period_to'=>'2026-08-16','status'=>'draft','total_amount'=>0,'created_by'=>$developer->id]);

        $this->actingAs($developer)->deleteJson('/api/payouts/'.$payout->id)->assertNoContent();
        $this->assertDatabaseMissing('payouts',['id'=>$payout->id]);
    }

    public function test_failed_creation_rolls_back_payout_and_audit_log(): void
    {
        $leader = $this->user(UserRole::GuildLeader);
        $payoutsBefore = Payout::query()->count();
        $auditLogsBefore = \App\Models\AuditLog::query()->count();

        $this->actingAs($leader)->postJson('/api/payouts', [
            'period_from' => '1999-01-01',
            'period_to' => '1999-01-31',
        ])->assertUnprocessable();

        self::assertSame($payoutsBefore, Payout::query()->count());
        self::assertSame($auditLogsBefore, \App\Models\AuditLog::query()->count());
    }

    public function test_creation_calculates_and_pays_earnings_atomically(): void
    {
        $leader = $this->user(UserRole::GuildLeader);
        [, $player] = $this->memberWithPlayer('PaidOnCreate');
        $definition = ActivityDefinition::query()->create([
            'name' => 'Paid payout '.uniqid(),
            'type' => 'prime',
            'is_active' => true,
        ]);
        $activity = Activity::query()->create([
            'activity_definition_id' => $definition->id,
            'occurred_at' => '2026-08-16 12:00:00+03',
            'gold_value' => 400,
            'completed_at' => '2026-08-16 12:10:00+03',
            'created_by' => $leader->id,
        ]);
        $activity->players()->attach($player->id, ['created_at' => now()]);
        $earning = PrimePlayerEarning::query()->create([
            'activity_id' => $activity->id,
            'player_id' => $player->id,
            'nickname_snapshot' => $player->nickname,
            'prime_gold_value_snapshot' => 400,
            'participants_count_snapshot' => 1,
            'player_share' => 400,
            'status' => 'pending',
        ]);
        TreasuryTransaction::query()->create([
            'type' => 'manual_income',
            'amount' => 1000,
            'balance_after' => 1000,
            'description' => 'Test balance',
            'created_by' => $leader->id,
        ]);

        $response = $this->actingAs($leader)->postJson('/api/payouts', [
            'period_from' => '2026-08-16',
            'period_to' => '2026-08-16',
        ])->assertCreated()->assertJsonPath('status', 'paid')->assertJsonPath('total_amount', 400);

        $payoutId = $response->json('id');
        $this->assertDatabaseHas('prime_player_earnings', ['id' => $earning->id, 'payout_id' => $payoutId, 'status' => 'paid']);
        $this->assertDatabaseHas('payout_players', ['payout_id' => $payoutId, 'player_id' => $player->id, 'status' => 'paid', 'amount' => 400]);
        self::assertSame(600, (int) TreasuryTransaction::query()->latest('id')->value('balance_after'));
    }

    public function test_activity_from_cancelled_payout_can_be_calculated_again_across_months(): void
    {
        $leader = $this->user(UserRole::GuildLeader);
        [, $player] = $this->memberWithPlayer('AcrossMonths');
        $definition = ActivityDefinition::query()->create([
            'name' => 'Cross-month '.uniqid(),
            'type' => 'prime',
            'is_active' => true,
        ]);
        $activity = Activity::query()->create([
            'activity_definition_id' => $definition->id,
            'occurred_at' => '2026-07-31 23:30:00+03',
            'gold_value' => 500,
            'created_by' => $leader->id,
        ]);
        $activity->players()->attach($player->id, ['created_at' => now()]);
        PrimePlayerEarning::query()->create([
            'activity_id' => $activity->id,
            'player_id' => $player->id,
            'nickname_snapshot' => $player->nickname,
            'prime_gold_value_snapshot' => 500,
            'participants_count_snapshot' => 1,
            'player_share' => 500,
            'status' => 'pending',
        ]);

        $cancelled = Payout::query()->create([
            'period_from' => '2026-07-01',
            'period_to' => '2026-07-31',
            'status' => 'cancelled',
            'total_amount' => 0,
            'created_by' => $leader->id,
        ]);
        $cancelled->activities()->attach($activity->id);
        $draft = Payout::query()->create([
            'period_from' => '2026-07-01',
            'period_to' => '2026-08-31',
            'status' => 'draft',
            'total_amount' => 0,
            'created_by' => $leader->id,
        ]);

        $this->actingAs($leader)->postJson('/api/payouts/'.$draft->id.'/calculate')
            ->assertOk()
            ->assertJsonPath('status', 'calculated')
            ->assertJsonPath('total_amount', 500);

        $this->assertDatabaseMissing('payout_activities', ['payout_id' => $cancelled->id, 'activity_id' => $activity->id]);
        $this->assertDatabaseHas('payout_activities', ['payout_id' => $draft->id, 'activity_id' => $activity->id]);
    }

    private function user(UserRole $role): User
    {
        $suffix=str_replace('.','',uniqid('',true));
        $user=User::query()->create(['discord_id'=>$suffix,'discord_username'=>'payout-'.$suffix]);
        $user->forceFill(['role'=>$role,'roles'=>[$role->value]])->save();
        Player::query()->create(['nickname'=>'Payout'.$user->id,'class'=>PlayerClass::Melee,'is_active'=>false])
            ->forceFill(['user_id'=>$user->id])->save();
        return $user;
    }

    private function memberWithPlayer(string $prefix): array
    {
        $user=$this->user(UserRole::Member);
        $user->player()->delete();
        $player=Player::query()->create(['nickname'=>$prefix.'-'.substr($user->discord_id,-8),'class'=>PlayerClass::Melee,'is_active'=>true]);
        $player->forceFill(['user_id'=>$user->id])->save();
        $user->setRelation('player',$player);
        return [$user,$player];
    }
}
