<?php
namespace App\Actions;
use App\Jobs\SendDiscordUserNotification;
use App\Models\Auction;use App\Models\AuctionAutoBid;use App\Models\AuctionBid;use App\Models\Player;use App\Services\ArmoryNotificationService;use App\Services\AuditService;use Illuminate\Support\Facades\DB;use Illuminate\Validation\ValidationException;
final class PlaceAuctionBid
{
 public function __construct(private readonly AuditService $audit,private readonly ArmoryNotificationService $notifications){}
 public function execute(Auction $auction,Player $player,int $amount):AuctionBid
 {
  return DB::transaction(function()use($auction,$player,$amount){
   $locked=Auction::query()->with('item:id,item_name')->lockForUpdate()->findOrFail($auction->id);
   if($locked->status!=='active')throw ValidationException::withMessages(['auction'=>__('domain.auction.inactive')]);
   if($locked->ends_at->isPast())throw ValidationException::withMessages(['auction'=>__('domain.auction.bidding_closed')]);
   $previous=AuctionBid::query()->with('player.user:id,discord_id')->where('auction_id',$locked->id)->orderByDesc('id')->lockForUpdate()->first();
   if($previous&&!AuctionAutoBid::query()->where('auction_id',$locked->id)->exists())AuctionAutoBid::query()->create(['auction_id'=>$locked->id,'player_id'=>$previous->player_id,'max_amount'=>$previous->amount]);
   $minimum=$previous?(int)$previous->amount+(int)$locked->minimum_step:(int)$locked->starting_bid;
   if($amount<$minimum)throw ValidationException::withMessages(['amount'=>__('domain.auction.minimum_bid',['amount'=>$minimum])]);
   $own=AuctionAutoBid::query()->where('auction_id',$locked->id)->where('player_id',$player->id)->lockForUpdate()->first();
   if($own&&$amount<=(int)$own->max_amount)throw ValidationException::withMessages(['amount'=>__('domain.auction.maximum_too_low',['amount'=>$own->max_amount])]);
   if($own)$own->update(['max_amount'=>$amount]);else AuctionAutoBid::query()->create(['auction_id'=>$locked->id,'player_id'=>$player->id,'max_amount'=>$amount]);
   $limits=AuctionAutoBid::query()->where('auction_id',$locked->id)->with('player.user:id,discord_id')->orderByDesc('max_amount')->orderBy('created_at')->orderBy('id')->lockForUpdate()->get();
   $winner=$limits->first();$second=$limits->get(1);
   $visible=$second?min((int)$winner->max_amount,(int)$second->max_amount+(int)$locked->minimum_step):(int)$locked->starting_bid;
   if(!$previous||$previous->player_id!==$winner->player_id||(int)$previous->amount!==$visible)$bid=AuctionBid::query()->create(['auction_id'=>$locked->id,'player_id'=>$winner->player_id,'amount'=>$visible,'is_auto'=>$winner->player_id!==$player->id||$visible<$amount]);else $bid=$previous;
   $extended=false;$oldEnds=$locked->ends_at;
   if($locked->ends_at->diffInSeconds(now())<=$locked->extension_minutes*60){$locked->update(['ends_at'=>now()->addMinutes($locked->extension_minutes),'extensions_count'=>$locked->extensions_count+1]);$extended=true;}
   $this->audit->record('auction.proxy_bid_placed',$bid,null,['auction_id'=>$locked->id,'player_id'=>$player->id,'visible_amount'=>$visible,'maximum_updated'=>true,'extended'=>$extended,'old_ends_at'=>$oldEnds->toISOString(),'new_ends_at'=>$locked->fresh()->ends_at->toISOString()]);
   $notify=[];
   if($previous&&$previous->player_id!==$winner->player_id&&$previous->player?->user?->discord_id)$notify[(string)$previous->player->user->discord_id]='Вашу ставку на «'.$locked->item->item_name.'» перебили. Текущая цена: **'.number_format($visible,0,'',' ').' жетонов**.';
   if($winner->player_id!==$player->id&&$player->user?->discord_id)$notify[(string)$player->user->discord_id]='Ваш максимум на «'.$locked->item->item_name.'» автоматически перебит. Текущая цена: **'.number_format($visible,0,'',' ').' жетонов**.';
   foreach($notify as $discordId=>$message)DB::afterCommit(function()use($discordId,$message,$locked){SendDiscordUserNotification::dispatch($discordId,'Ставка перебита',$message);$user=\App\Models\User::query()->where('discord_id',$discordId)->first();if($user)$this->notifications->notify($user,'auction_outbid','Ставка перебита',str_replace('**','',$message),'/auctions/'.$locked->id);});
   return $bid->load('player.user:id,discord_id,discord_username,discord_display_name,discord_avatar');
  });
 }
}
