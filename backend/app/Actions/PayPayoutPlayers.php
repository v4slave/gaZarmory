<?php

namespace App\Actions;

use App\Jobs\SendPlayerPayoutNotification;
use App\Models\Payout;
use App\Models\PrimePlayerEarning;
use App\Models\TreasuryTransaction;
use App\Services\AuditService;
use App\Services\ArmoryNotificationService;
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
            if($locked->status!=='calculated')throw ValidationException::withMessages(['payout'=>'Выдавать можно только рассчитанный нахрюк.']);
            $rows=$locked->players()->with('player.user:id,discord_id')->whereIn('player_id',$playerIds)->where('status','pending')->lockForUpdate()->get();
            if($rows->isEmpty())throw ValidationException::withMessages(['players'=>'Не выбраны ожидающие выплаты игроки.']);
            $amount=(int)$rows->sum('amount');
            DB::select('SELECT pg_advisory_xact_lock(?)',[834721]);
            $balance=(int)(TreasuryTransaction::query()->latest('id')->value('balance_after')??0);
            if($balance<$amount)throw ValidationException::withMessages(['treasury'=>'В казне недостаточно золота. Требуется '.$amount.', доступно '.$balance.'.']);
            TreasuryTransaction::query()->create(['type'=>'payout','amount'=>-$amount,'balance_after'=>$balance-$amount,'description'=>'Нахрюк #'.$locked->id.': фактически выдано '.count($rows).' игрокам','related_entity_type'=>Payout::class,'related_entity_id'=>$locked->id,'created_by'=>$userId]);
            $ids=$rows->pluck('player_id');
            $earningsByPlayer=PrimePlayerEarning::query()
                ->where('payout_id',$locked->id)
                ->whereIn('player_id',$ids)
                ->with('activity.definition:id,name')
                ->get()
                ->groupBy('player_id');
            PrimePlayerEarning::query()->where('payout_id',$locked->id)->whereIn('player_id',$ids)->where('status','pending')->update(['status'=>'paid']);
            $locked->players()->whereIn('player_id',$ids)->update(['status'=>'paid','paid_at'=>now()]);
            $remaining=$locked->players()->where('status','pending')->exists();
            if(!$remaining)$locked->update(['status'=>'paid','paid_at'=>now()]);
            $this->audit->record('payout.players_paid',$locked,null,['player_ids'=>$ids->all(),'amount'=>$amount,'completed'=>!$remaining]);
            foreach($rows as $row){$discordId=$row->player?->user?->discord_id;if(!$discordId)continue;$details=$earningsByPlayer->get($row->player_id,collect())->groupBy(fn($earning)=>$earning->activity?->definition?->name??'Активность')->map(fn($items,$name)=>'• '.$name.': **'.number_format($items->sum('player_share'),0,'',' ').'**')->implode("\n");DB::afterCommit(fn()=>SendPlayerPayoutNotification::dispatch((string)$discordId,'Выплата по нахрюку #'.$locked->id,'Вам выдано **'.number_format($row->amount,0,'',' ').' золота**.'.($details?"\n\nРасшифровка:\n".$details:'')));}
            return $locked->refresh()->load('players');
        }); } catch (ValidationException $exception) { if(isset($exception->errors()['treasury'])){$recipients=$this->notifications->financialLeaders();$requester=User::query()->find($userId);if($requester)$recipients->push($requester);$this->notifications->notify($recipients,'insufficient_gold','Не хватает золота',$exception->errors()['treasury'][0],'/payouts/'.$payout->id);}throw $exception; }
    }
}
