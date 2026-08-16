<?php

namespace Tests\Feature;

use App\Enums\PlayerClass;
use App\Enums\UserRole;
use App\Models\Payout;
use App\Models\Player;
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

    private function user(UserRole $role): User
    {
        $suffix=str_replace('.','',uniqid('',true));
        $user=User::query()->create(['discord_id'=>$suffix,'discord_username'=>'payout-'.$suffix]);
        $user->forceFill(['role'=>$role,'roles'=>[$role->value]])->save();
        return $user;
    }

    private function memberWithPlayer(string $prefix): array
    {
        $user=$this->user(UserRole::Member);
        $player=Player::query()->create(['nickname'=>$prefix.'-'.substr($user->discord_id,-8),'class'=>PlayerClass::Melee,'is_active'=>true]);
        $player->forceFill(['user_id'=>$user->id])->save();
        $user->setRelation('player',$player);
        return [$user,$player];
    }
}
