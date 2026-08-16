<?php
namespace App\Actions;
use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\PrimePlayerEarning;
use App\Services\AuditService;
use App\Services\PrimePayoutCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
final class CalculatePrimeShares
{
    public function __construct(private readonly PrimePayoutCalculator $calculator,private readonly AuditService $audit){}
    public function execute(Activity $activity):Activity
    {
        return DB::transaction(function()use($activity){
            $locked=Activity::query()->lockForUpdate()->with(['definition','players','loot'])->findOrFail($activity->id);
            if(!in_array($locked->definition->type,[ActivityType::Prime,ActivityType::MiniActivity],true))throw ValidationException::withMessages(['activity'=>'Для этого типа события начисления недоступны.']);
            if($locked->earnings()->exists())throw ValidationException::withMessages(['activity'=>'Эта активность уже рассчитана.']);
            if($locked->players->isEmpty())throw ValidationException::withMessages(['players'=>'Добавьте хотя бы одного участника.']);
            $goldValue=$locked->loot->sum(fn($item)=>$item->quantity*$item->unit_price);
            $result=$this->calculator->calculate($goldValue,$locked->players->count());
            $locked->update(['gold_value'=>$goldValue]+($locked->definition->type===ActivityType::MiniActivity?['completed_at'=>now()]:[]));
            foreach($locked->players as $player)PrimePlayerEarning::query()->create(['activity_id'=>$locked->id,'player_id'=>$player->id,'nickname_snapshot'=>$player->nickname,'prime_gold_value_snapshot'=>$goldValue,'participants_count_snapshot'=>$locked->players->count(),'player_share'=>$result['player_share'],'status'=>'pending']);
            $action=$locked->definition->type===ActivityType::Prime?'prime.calculated':'mini_activity.completed';
            $this->audit->record($action,$locked,null,$result+['gold_value'=>$goldValue,'participants_count'=>$locked->players->count()]);
            return $locked->refresh()->load(['definition','players.group','loot','earnings']);
        });
    }
}
