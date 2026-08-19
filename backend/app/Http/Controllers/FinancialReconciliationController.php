<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Auction;
use App\Models\LootImport;
use App\Models\Payout;
use App\Models\PrimePlayerEarning;
use App\Models\TreasuryItem;
use App\Models\TreasuryItemTransaction;
use App\Models\TreasuryTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class FinancialReconciliationController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless($request->user()->canHandleTreasuryItems(), 403);

        $checks = [
            $this->goldCheck(),
            $this->itemCheck(),
            $this->payoutCheck(),
            $this->reservationCheck(),
            $this->staleCheck(),
            $this->suspiciousCheck(),
        ];
        $issues = collect($checks)->sum('issues_count');
        $critical = collect($checks)->sum('critical_count');

        return response()->json([
            'checked_at' => now()->toISOString(),
            'status' => $critical > 0 ? 'critical' : ($issues > 0 ? 'warning' : 'ok'),
            'summary' => ['checks'=>count($checks),'passed'=>collect($checks)->where('issues_count',0)->count(),'issues'=>$issues,'critical'=>$critical],
            'checks' => $checks,
        ]);
    }

    private function goldCheck(): array
    {
        $transactions = TreasuryTransaction::query()->orderBy('id')->get(['id','type','amount','balance_after','description','created_at']);
        $issues = []; $running = 0;
        foreach ($transactions as $transaction) {
            $running += (int) $transaction->amount;
            if ($running !== (int) $transaction->balance_after) $issues[] = $this->issue('critical', 'Разрыв баланса в транзакции #'.$transaction->id, 'Ожидалось '.$running.', записано '.$transaction->balance_after, 'treasury_transaction', $transaction->id);
        }
        $current = (int) ($transactions->last()?->balance_after ?? 0);
        if ($running !== $current && !$issues) $issues[] = $this->issue('critical','Сумма транзакций не совпадает с текущим балансом','По движениям '.$running.', текущий баланс '.$current);
        return $this->check('gold','Золото по транзакциям','Сверка последовательности всех движений и текущего баланса',$issues,['calculated_balance'=>$running,'current_balance'=>$current,'transactions'=>$transactions->count()]);
    }

    private function itemCheck(): array
    {
        $movementTotals = TreasuryItemTransaction::query()->selectRaw('treasury_item_id, SUM(quantity_delta) AS total')->groupBy('treasury_item_id')->pluck('total','treasury_item_id');
        $items = TreasuryItem::query()->orderBy('id')->get(); $issues = [];
        foreach ($items as $item) {
            $expected = (int) ($movementTotals[$item->id] ?? 0);
            if ($expected !== (int) $item->quantity) $issues[] = $this->issue('critical','Остаток «'.$item->item_name.'» не совпадает с движениями','По движениям '.$expected.', в казне '.$item->quantity,'treasury_item',$item->id);
        }
        return $this->check('items','Предметы по движениям','Сумма движений каждого предмета против текущего остатка',$issues,['items'=>$items->count(),'movements'=>TreasuryItemTransaction::query()->count()]);
    }

    private function payoutCheck(): array
    {
        $payouts = Payout::query()->with('players:id,payout_id,amount,status,paid_at')->orderBy('id')->get(); $issues = [];
        $earnings = PrimePlayerEarning::query()->whereNotNull('payout_id')->selectRaw('payout_id, SUM(player_share) AS total, COUNT(*) AS rows')->groupBy('payout_id')->get()->keyBy('payout_id');
        foreach ($payouts as $payout) {
            if ($payout->status === 'draft' || $payout->status === 'cancelled') continue;
            $playersTotal = (int) $payout->players->sum('amount');
            $earningsTotal = (int) ($earnings->get($payout->id)?->total ?? 0);
            if ($playersTotal !== (int) $payout->total_amount) $issues[] = $this->issue('critical','Нахрюк #'.$payout->id.': сумма игроков не совпадает','Игрокам '.$playersTotal.', итог '.$payout->total_amount,'payout',$payout->id);
            if ($earningsTotal !== (int) $payout->total_amount) $issues[] = $this->issue('critical','Нахрюк #'.$payout->id.': начисления не совпадают','Начисления '.$earningsTotal.', итог '.$payout->total_amount,'payout',$payout->id);
            if ($payout->status === 'paid') {
                $gold = (int) TreasuryTransaction::query()->where('related_entity_type', Payout::class)->where('related_entity_id',$payout->id)->sum('amount');
                if ($gold !== -(int)$payout->total_amount) $issues[] = $this->issue('critical','Нахрюк #'.$payout->id.': неверное списание золота','Ожидалось '.(-$payout->total_amount).', списано '.$gold,'payout',$payout->id);
                if ($payout->players->contains(fn ($row) => $row->status !== 'paid')) $issues[] = $this->issue('critical','Нахрюк #'.$payout->id.': не все строки игроков оплачены','Статус нахрюка paid, но есть строки с другим статусом','payout',$payout->id);
            }
        }
        $orphaned = PrimePlayerEarning::query()->whereNotNull('payout_id')->whereDoesntHave('payout')->get(['id','payout_id']);
        foreach ($orphaned as $earning) $issues[] = $this->issue('critical','Начисление #'.$earning->id.' ссылается на отсутствующий нахрюк','payout_id '.$earning->payout_id,'earning',$earning->id);
        return $this->check('payouts','Начисления и нахрюки','Сверка начислений, строк игроков, итогов и списаний золота',$issues,['payouts'=>$payouts->count(),'linked_earnings'=>(int)$earnings->sum('rows')]);
    }

    private function reservationCheck(): array
    {
        $expected = Auction::query()->where('status','active')->selectRaw('treasury_item_id, SUM(quantity) AS total')->groupBy('treasury_item_id')->pluck('total','treasury_item_id');
        $items = TreasuryItem::query()->where(fn ($query) => $query->where('reserved_quantity','>',0)->orWhereIn('id',$expected->keys()))->get(); $issues = [];
        foreach ($items as $item) {
            $value = (int) ($expected[$item->id] ?? 0);
            if ($value !== (int)$item->reserved_quantity) $issues[] = $this->issue('critical','Резерв «'.$item->item_name.'» не совпадает с активными аукционами','Активные лоты требуют '.$value.', зарезервировано '.$item->reserved_quantity,'treasury_item',$item->id);
            if ((int)$item->reserved_quantity > (int)$item->quantity) $issues[] = $this->issue('critical','Резерв «'.$item->item_name.'» превышает остаток','Резерв '.$item->reserved_quantity.', остаток '.$item->quantity,'treasury_item',$item->id);
        }
        return $this->check('reservations','Резервы аукционов','Зарезервированные остатки против активных лотов',$issues,['active_auctions'=>Auction::query()->where('status','active')->count(),'reserved_items'=>$items->count()]);
    }

    private function staleCheck(): array
    {
        $issues = [];
        foreach (Auction::query()->where('status','active')->where('ends_at','<',now())->get(['id','ends_at']) as $row) $issues[] = $this->issue('warning','Аукцион #'.$row->id.' истёк, но остаётся активным','Завершение '.$row->ends_at,'auction',$row->id);
        foreach (Auction::query()->where('status','draft')->where('created_at','<',now()->subDays(7))->get(['id','created_at']) as $row) $issues[] = $this->issue('warning','Черновик аукциона #'.$row->id.' старше 7 дней','Создан '.$row->created_at,'auction',$row->id);
        foreach (Payout::query()->whereIn('status',['draft','calculated'])->where('updated_at','<',now()->subDay())->get(['id','status','updated_at']) as $row) $issues[] = $this->issue('warning','Нахрюк #'.$row->id.' завис в статусе '.$row->status,'Не изменялся с '.$row->updated_at,'payout',$row->id);
        foreach (LootImport::query()->where('status','draft')->where('created_at','<',now()->subDay())->get(['id','activity_id','created_at']) as $row) $issues[] = $this->issue('warning','Импорт лута #'.$row->id.' не подтверждён более суток','Активность #'.$row->activity_id,'loot_import',$row->id);
        foreach (Activity::query()->whereNull('completed_at')->where('created_at','<',now()->subDays(7))->get(['id','created_at']) as $row) $issues[] = $this->issue('warning','Активность #'.$row->id.' не завершена более 7 дней','Создана '.$row->created_at,'activity',$row->id);
        return $this->check('stale','Незавершённые операции','Просроченные аукционы и давно не изменявшиеся черновики',$issues);
    }

    private function suspiciousCheck(): array
    {
        $issues = [];
        foreach (TreasuryTransaction::query()->where('balance_after','<',0)->get(['id','balance_after']) as $row) $issues[] = $this->issue('critical','Отрицательный баланс после транзакции #'.$row->id,'Баланс '.$row->balance_after,'treasury_transaction',$row->id);
        foreach (TreasuryItem::query()->where(fn ($query) => $query->where('quantity','<',0)->orWhere('reserved_quantity','<',0)->orWhere('unit_value','<',0))->get() as $row) $issues[] = $this->issue('critical','Отрицательное значение у «'.$row->item_name.'»','Остаток '.$row->quantity.', резерв '.$row->reserved_quantity.', цена '.$row->unit_value,'treasury_item',$row->id);
        foreach (Auction::query()->where(fn ($query) => $query->where('quantity','<=',0)->orWhere('starting_bid','<',0)->orWhere('minimum_step','<=',0)->orWhere('winning_bid','<',0))->get(['id']) as $row) $issues[] = $this->issue('critical','Некорректное значение в аукционе #'.$row->id,'Количество или денежное значение вне допустимого диапазона','auction',$row->id);
        foreach (PrimePlayerEarning::query()->where('player_share','<',0)->get(['id','player_share']) as $row) $issues[] = $this->issue('critical','Отрицательное начисление #'.$row->id,'Сумма '.$row->player_share,'earning',$row->id);
        $paidWithoutPayout = PrimePlayerEarning::query()->where('status','paid')->whereNull('payout_id')->get(['id']);
        foreach ($paidWithoutPayout as $row) $issues[] = $this->issue('critical','Начисление #'.$row->id.' оплачено без нахрюка','Статус paid при пустом payout_id','earning',$row->id);
        return $this->check('suspicious','Подозрительные значения','Отрицательные значения и логически невозможные состояния',$issues);
    }

    private function check(string $key, string $title, string $description, array $issues, array $metrics = []): array
    {
        return ['key'=>$key,'title'=>$title,'description'=>$description,'status'=>collect($issues)->contains('severity','critical')?'critical':(count($issues)?'warning':'ok'),'issues_count'=>count($issues),'critical_count'=>collect($issues)->where('severity','critical')->count(),'metrics'=>$metrics,'issues'=>array_slice($issues,0,200)];
    }

    private function issue(string $severity, string $title, string $details, ?string $entityType = null, ?int $entityId = null): array
    {
        return compact('severity','title','details','entityType','entityId');
    }
}
