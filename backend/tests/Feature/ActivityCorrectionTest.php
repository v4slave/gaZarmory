<?php

namespace Tests\Feature;

use App\Enums\PlayerClass;
use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\ActivityDefinition;
use App\Models\ActivityLoot;
use App\Models\AuditLog;
use App\Models\Payout;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class ActivityCorrectionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pending_earnings_can_be_cancelled_corrected_and_recalculated(): void
    {
        [$leader, $activity, $players, $loot] = $this->calculatedMiniActivity();

        $this->actingAs($leader)->postJson('/api/activities/'.$activity->id.'/reopen', ['reason'=>'Ошибочно указана стоимость предмета'])
            ->assertOk()->assertJsonPath('completed_at', null)->assertJsonCount(0, 'earnings');
        $this->assertDatabaseMissing('prime_player_earnings', ['activity_id'=>$activity->id]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id'=>$leader->id, 'action'=>'activity.reopened_for_correction',
            'entity_type'=>Activity::class, 'entity_id'=>$activity->id,
        ]);
        $log = AuditLog::query()->where('action','activity.reopened_for_correction')->where('entity_id',$activity->id)->latest('id')->firstOrFail();
        self::assertSame('Ошибочно указана стоимость предмета', $log->new_values['reason']);

        $this->actingAs($leader)->patchJson('/api/activities/'.$activity->id.'/loot/'.$loot->id, ['unit_price'=>900])
            ->assertOk()->assertJsonPath('unit_price',900);
        $this->actingAs($leader)->deleteJson('/api/activities/'.$activity->id.'/players/'.$players[1]->id)->assertNoContent();
        $this->actingAs($leader)->postJson('/api/activities/'.$activity->id.'/complete')->assertOk()->assertJsonPath('gold_value',900);
        self::assertSame(1, $activity->earnings()->count());
        self::assertSame(900, (int)$activity->earnings()->first()->player_share);
    }

    public function test_paid_or_payout_linked_earnings_cannot_be_cancelled(): void
    {
        [$leader, $activity] = $this->calculatedMiniActivity();
        $activity->earnings()->first()->update(['status'=>'paid']);
        $this->actingAs($leader)->postJson('/api/activities/'.$activity->id.'/reopen', ['reason'=>'Попытка изменить выплаченную активность'])->assertStatus(409);

        [$leader2, $activity2] = $this->calculatedMiniActivity();
        $payout = Payout::query()->create(['period_from'=>now()->toDateString(),'period_to'=>now()->toDateString(),'status'=>'calculated','total_amount'=>500,'created_by'=>$leader2->id]);
        $activity2->earnings()->update(['payout_id'=>$payout->id]);
        $this->actingAs($leader2)->postJson('/api/activities/'.$activity2->id.'/reopen', ['reason'=>'Начисления уже попали в нахрюк'])->assertStatus(409);
        self::assertGreaterThan(0, $activity2->earnings()->count());
    }

    public function test_correction_reason_is_required(): void
    {
        [$leader, $activity] = $this->calculatedMiniActivity();
        $this->actingAs($leader)->postJson('/api/activities/'.$activity->id.'/reopen', ['reason'=>'ошибка'])->assertUnprocessable();
    }

    private function calculatedMiniActivity(): array
    {
        $suffix = str_replace('.', '', uniqid('', true));
        $leader = User::query()->create(['discord_id'=>$suffix,'discord_username'=>'correction'.$suffix]);
        $leader->forceFill(['role'=>UserRole::GuildLeader,'roles'=>[UserRole::GuildLeader->value]])->save();
        $leaderPlayer = Player::query()->create(['nickname'=>'Lead'.substr($suffix,-8),'class'=>PlayerClass::Melee,'is_active'=>true]);
        $leaderPlayer->forceFill(['user_id'=>$leader->id])->save();
        $definition = ActivityDefinition::query()->create(['name'=>'Correction'.uniqid(),'type'=>'mini_activity','is_active'=>true]);
        $activity = Activity::query()->create(['activity_definition_id'=>$definition->id,'occurred_at'=>now(),'created_by'=>$leader->id]);
        $players = collect(['Alpha','Beta'])->map(fn ($name) => Player::query()->create(['nickname'=>$name.substr(uniqid(),-6),'class'=>PlayerClass::Mage,'is_active'=>true]));
        $activity->players()->attach($players->pluck('id')->all(), ['created_at'=>now()]);
        $loot = ActivityLoot::query()->create(['activity_id'=>$activity->id,'item_name'=>'Correction loot '.uniqid(),'quantity'=>1,'unit_price'=>500,'created_by'=>$leader->id]);
        $this->actingAs($leader)->postJson('/api/activities/'.$activity->id.'/complete')->assertOk();
        return [$leader,$activity->fresh(),$players,$loot];
    }
}
