<?php
namespace App\Actions;
use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\Player;
use App\Jobs\SendDiscordNotification;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
final class PlaceAuctionBid
{
    public function __construct(private readonly AuditService $audit){}
    public function execute(Auction $auction,Player $player,int $amount):AuctionBid
    {
        return DB::transaction(function()use($auction,$player,$amount){$locked=Auction::query()->with('item:id,item_name')->lockForUpdate()->findOrFail($auction->id);if($locked->status!=='active')throw ValidationException::withMessages(['auction'=>'Лот не активен.']);if($locked->ends_at->isPast())throw ValidationException::withMessages(['auction'=>'Время приёма ставок завершено.']);$top=AuctionBid::query()->where('auction_id',$locked->id)->orderByDesc('amount')->orderBy('created_at')->orderBy('id')->lockForUpdate()->first();$minimum=$top?$top->amount+$locked->minimum_step:$locked->starting_bid;if($amount<$minimum)throw ValidationException::withMessages(['amount'=>'Минимальная ставка: '.$minimum.' золота.']);$bid=AuctionBid::query()->create(['auction_id'=>$locked->id,'player_id'=>$player->id,'amount'=>$amount]);$this->audit->record('auction.bid_placed',$bid,null,['auction_id'=>$locked->id,'player_id'=>$player->id,'amount'=>$amount]);DB::afterCommit(fn()=>SendDiscordNotification::dispatch('Новая максимальная ставка', '**'.$player->nickname.'** поставил **'.number_format($amount,0,'',' ').' золота** на «'.$locked->item->item_name.'».'));return $bid->load('player:id,nickname');});
    }
}
