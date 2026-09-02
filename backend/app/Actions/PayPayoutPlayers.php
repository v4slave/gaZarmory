<?php

namespace App\Actions;

use App\Jobs\SendDiscordNotification;
use App\Models\Payout;
use App\Models\PrimePlayerEarning;
use App\Models\TreasuryTransaction;
use App\Services\AuditService;
use App\Services\ArmoryNotificationService;
use App\Support\DiscordPayoutCard;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PayPayoutPlayers
{
    public function __construct(private readonly AuditService $audit,private readonly ArmoryNotificationService $notifications) {}
    public function execute(Payout $payout, array $playerIds, int $userId): Payout
    {
        try { return DB::transaction(function () use ($payout,$playerIds,$userId): Payout {
            $locked=Payout::query()->lockForUpdate()->findOrFail($payout->id);
            if($locked->status!=='calculated')throw ValidationException::withMessages(['payout'=>__('domain.payout.pay_calculated_only')]);
            $rows=$locked->players()->whereIn('player_id',$playerIds)->where('status','pending')->lockForUpdate()->get();
            if($rows->isEmpty())throw ValidationException::withMessages(['players'=>__('domain.payout.no_pending_players')]);
            $amount=(int)$rows->sum('amount');
            DB::select('SELECT pg_advisory_xact_lock(?)',[834721]);
            $balance=(int)(TreasuryTransaction::query()->latest('id')->value('balance_after')??0);
            if($balance<$amount)throw ValidationException::withMessages(['treasury'=>__('domain.payout.insufficient_gold', ['required'=>$amount, 'available'=>$balance])]);
            TreasuryTransaction::query()->create(['type'=>'payout','amount'=>-$amount,'balance_after'=>$balance-$amount,'description'=>'Нахрюк #'.$locked->id.': фактически выдано '.count($rows).' игрокам','related_entity_type'=>Payout::class,'related_entity_id'=>$locked->id,'created_by'=>$userId]);
            $ids=$rows->pluck('player_id');
            $earnings=PrimePlayerEarning::query()
                ->where('payout_id',$locked->id)
                ->whereIn('player_id',$ids)
                ->with('activity.definition:id,name')
                ->get();
            PrimePlayerEarning::query()->where('payout_id',$locked->id)->whereIn('player_id',$ids)->where('status','pending')->update(['status'=>'paid']);
            $locked->players()->whereIn('player_id',$ids)->update(['status'=>'paid','paid_at'=>now()]);
            $remaining=$locked->players()->where('status','pending')->exists();
            if(!$remaining)$locked->update(['status'=>'paid','paid_at'=>now()]);
            $this->audit->record('payout.players_paid',$locked,null,['player_ids'=>$ids->all(),'amount'=>$amount,'completed'=>!$remaining]);
            $breakdown=$earnings->groupBy(fn($earning)=>$earning->activity?->definition?->name??'Активность')->map(fn($items)=>(int)$items->sum('player_share'))->all();
            DB::afterCommit(fn()=>SendDiscordNotification::dispatch('Выплата начислена','Золото выдано участникам. Откройте расчёт, чтобы посмотреть детали.','green','payouts',DiscordPayoutCard::paid($locked->id,$amount,$rows->count(),$breakdown)));
            return $locked->refresh()->load('players');
        }); } catch (ValidationException $exception) { if(isset($exception->errors()['treasury'])){$recipients=$this->notifications->financialLeaders();$requester=User::query()->find($userId);if($requester)$recipients->push($requester);$this->notifications->notify($recipients,'insufficient_gold','Не хватает золота',$exception->errors()['treasury'][0],'/payouts/'.$payout->id);}throw $exception; }
    }
}
