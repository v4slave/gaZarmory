<?php
namespace App\Actions;
use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\TreasuryItem;
use App\Models\TreasuryItemTransaction;
use App\Models\TreasuryTransaction;
use App\Jobs\SendDiscordNotification;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
final class FinishAuction
{
    public function __construct(private readonly AuditService $audit){}
    public function execute(Auction $auction,int $userId):Auction
    {
        return DB::transaction(function()use($auction,$userId){$locked=Auction::query()->lockForUpdate()->findOrFail($auction->id);if($locked->status!=='active')throw ValidationException::withMessages(['auction'=>'Завершить можно только активный лот.']);if($locked->ends_at->isFuture())throw ValidationException::withMessages(['auction'=>'Аукцион ещё не завершился по времени.']);$item=TreasuryItem::query()->lockForUpdate()->findOrFail($locked->treasury_item_id);$winner=AuctionBid::query()->with('player:id,nickname')->where('auction_id',$locked->id)->orderByDesc('amount')->orderBy('created_at')->orderBy('id')->lockForUpdate()->first();if(!$winner){$item->decrement('reserved_quantity',$locked->quantity);$locked->update(['status'=>'finished','finished_at'=>now()]);$this->audit->record('auction.finished_without_bids',$locked,['status'=>'active'],['status'=>'finished']);DB::afterCommit(fn()=>SendDiscordNotification::dispatch('Аукцион завершён', 'На лот «'.$item->item_name.'» не было ставок.','red'));return $locked->refresh()->load(['item','winner']);}$item->quantity-=$locked->quantity;$item->reserved_quantity-=$locked->quantity;$item->save();TreasuryItemTransaction::query()->create(['treasury_item_id'=>$item->id,'type'=>'auction_sale','quantity_delta'=>-$locked->quantity,'auction_id'=>$locked->id,'reason'=>'Продажа на аукционе #'.$locked->id,'created_by'=>$userId]);DB::select('SELECT pg_advisory_xact_lock(?)',[834721]);$previous=(int)(TreasuryTransaction::query()->latest('id')->value('balance_after')??0);TreasuryTransaction::query()->create(['type'=>'auction_income','amount'=>$winner->amount,'balance_after'=>$previous+$winner->amount,'description'=>'Продажа '.$item->item_name.' на аукционе #'.$locked->id,'related_entity_type'=>Auction::class,'related_entity_id'=>$locked->id,'created_by'=>$userId]);$locked->update(['status'=>'finished','winner_player_id'=>$winner->player_id,'winning_bid'=>$winner->amount,'finished_at'=>now()]);$this->audit->record('auction.finished',$locked,['status'=>'active'],['status'=>'finished','winner_player_id'=>$winner->player_id,'winning_bid'=>$winner->amount]);DB::afterCommit(fn()=>SendDiscordNotification::dispatch('Аукцион завершён', '«'.$item->item_name.'» выиграл **'.$winner->player->nickname.'** за **'.number_format($winner->amount,0,'',' ').' золота**.','green'));return $locked->refresh()->load(['item','winner']);});
    }
}
