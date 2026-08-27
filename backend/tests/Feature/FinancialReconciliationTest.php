<?php

namespace Tests\Feature;

use App\Enums\PlayerClass;
use App\Enums\UserRole;
use App\Models\Payout;
use App\Models\Player;
use App\Models\TreasuryItem;
use App\Models\TreasuryTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class FinancialReconciliationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_financial_leader_sees_balance_and_inventory_discrepancies(): void
    {
        $leader = $this->user(UserRole::GuildLeader);
        $previous = (int) (TreasuryTransaction::query()->latest('id')->value('balance_after') ?? 0);
        TreasuryTransaction::query()->create(['type'=>'manual_income','amount'=>10,'balance_after'=>$previous + 999,'description'=>'test mismatch','created_by'=>$leader->id]);
        $item = TreasuryItem::query()->create(['item_name'=>'Mismatch'.uniqid(),'quantity'=>3,'reserved_quantity'=>0,'unit_value'=>10]);

        $response = $this->actingAs($leader)->getJson('/api/financial-reconciliation')->assertOk()->assertJsonPath('status','critical');
        $checks = collect($response->json('checks'))->keyBy('key');
        $this->assertGreaterThan(0, $checks['gold']['issues_count']);
        $this->assertGreaterThan(0, $checks['items']['issues_count']);
        $this->assertStringContainsString($item->item_name, $checks['items']['issues'][0]['title']);

        $english = $this->actingAs($leader)->withHeader('Accept-Language', 'en')
            ->getJson('/api/financial-reconciliation')->assertOk();
        $englishChecks = collect($english->json('checks'))->keyBy('key');
        $this->assertSame('Gold transaction balance', $englishChecks['gold']['title']);
        $this->assertSame('Item movement balance', $englishChecks['items']['title']);
        $this->assertStringNotContainsString('не совпадает', $englishChecks['items']['issues'][0]['title']);
    }

    public function test_reconciliation_is_read_only(): void
    {
        $developer = $this->user(UserRole::Developer);
        $before = [TreasuryTransaction::query()->count(), TreasuryItem::query()->count()];
        $this->actingAs($developer)->getJson('/api/financial-reconciliation')->assertOk();
        $this->assertSame($before, [TreasuryTransaction::query()->count(), TreasuryItem::query()->count()]);
    }

    public function test_member_and_micro_leader_cannot_run_financial_reconciliation(): void
    {
        foreach ([UserRole::Member, UserRole::MicroGuildLeader] as $role) {
            $this->actingAs($this->user($role))->getJson('/api/financial-reconciliation')->assertForbidden();
        }
    }

    public function test_payout_reconciliation_does_not_add_a_query_per_payout(): void
    {
        $developer = $this->user(UserRole::Developer);
        $queries = [];
        DB::listen(function () use (&$queries): void { $queries[] = true; });

        $this->actingAs($developer)->getJson('/api/financial-reconciliation')->assertOk();
        $baseline = count($queries);

        foreach (range(1, 20) as $index) {
            Payout::query()->create([
                'period_from'=>now()->toDateString(),
                'period_to'=>now()->toDateString(),
                'status'=>'calculated',
                'total_amount'=>$index,
                'created_by'=>$developer->id,
            ]);
        }

        $queries = [];
        $this->actingAs($developer)->getJson('/api/financial-reconciliation')->assertOk();
        $this->assertLessThanOrEqual($baseline + 2, count($queries));
    }

    private function user(UserRole $role): User
    {
        $suffix = str_replace('.', '', uniqid('', true));
        $user = User::query()->create(['discord_id'=>$suffix,'discord_username'=>'reconcile'.$suffix]);
        $user->forceFill(['roles'=>[$role->value],'role'=>$role])->save();
        $player = Player::query()->create(['nickname'=>'Rec'.substr($suffix,-8),'class'=>PlayerClass::Healer,'is_active'=>false]);
        $player->forceFill(['user_id'=>$user->id])->save();
        return $user;
    }
}
