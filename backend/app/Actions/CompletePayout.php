<?php
namespace App\Actions;
use App\Models\Payout;
use App\Models\PrimePlayerEarning;
use App\Models\TreasuryTransaction;
use App\Jobs\SendDiscordNotification;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
final class CompletePayout
{
    public function __construct(private readonly AuditService $audit){}
    public function execute(Payout $payout,int $userId):Payout
    {
        return DB::transaction(function()use($payout,$userId){$locked=Payout::query()->lockForUpdate()->findOrFail($payout->id);if($locked->status!=='calculated')throw ValidationException::withMessages(['payout'=>'Завершить можно только рассчитанную выплату.']);DB::select('SELECT pg_advisory_xact_lock(?)',[834721]);$balance=(int)(TreasuryTransaction::query()->latest('id')->value('balance_after')??0);if($balance<$locked->total_amount)throw ValidationException::withMessages(['treasury'=>'В казне недостаточно золота. Требуется '.$locked->total_amount.', доступно '.$balance.'.']);TreasuryTransaction::query()->create(['type'=>'payout','amount'=>-$locked->total_amount,'balance_after'=>$balance-$locked->total_amount,'description'=>'Нахрюк #'.$locked->id,'related_entity_type'=>Payout::class,'related_entity_id'=>$locked->id,'created_by'=>$userId]);PrimePlayerEarning::query()->where('payout_id',$locked->id)->where('status','pending')->update(['status'=>'paid']);$locked->players()->update(['status'=>'paid','paid_at'=>now()]);$locked->update(['status'=>'paid','paid_at'=>now()]);$this->audit->record('payout.completed',$locked,['status'=>'calculated'],['status'=>'paid','total_amount'=>$locked->total_amount]);DB::afterCommit(fn()=>SendDiscordNotification::dispatch('Нахрюк выплачен', 'Выплата #'.$locked->id.' завершена. Распределено **'.number_format($locked->total_amount,0,'',' ').' золота**.','green'));return $locked->refresh()->load('players');});
    }
}
