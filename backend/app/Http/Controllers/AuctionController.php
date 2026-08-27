<?php
namespace App\Http\Controllers;
use App\Models\Auction;
use App\Models\TreasuryItem;
use App\Services\AuditService;
use App\Actions\PlaceAuctionBid;
use App\Actions\FinishAuction;
use App\Jobs\SendDiscordNotification;
use App\Services\ArmoryNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
final class AuctionController extends Controller
{
    public function index(Request $request){$manager=$request->user()->canCreateAuctions();$data=$request->validate(['per_page'=>['nullable','integer','min:6','max:30']]);return Auction::query()->with(['item','winner.user:id,discord_id,discord_username,discord_display_name,discord_avatar','topBid.player.user:id,discord_id,discord_username,discord_display_name,discord_avatar'])->withCount('bids')->where(fn($q)=>$q->where('status','!=','finished')->orWhere('finished_at','>=',now()->subDays(3)))->when(!$manager,fn($q)=>$q->whereIn('status',['active','finished']))->when($manager&&$request->filled('status'),fn($q)=>$q->where('status',$request->string('status')->toString()))->orderByDesc('id')->paginate($data['per_page']??12);}
    public function activeCount(){return response()->json(['count'=>Auction::query()->where('status','active')->count()]);}
    public function show(Request $request,Auction $auction)
    {
        $visibleToMember = $auction->status === 'active'
            || ($auction->status === 'finished'
                && $auction->finished_at !== null
                && $auction->finished_at->gte(now()->subDays(3)));

        abort_if(!$request->user()->canCreateAuctions() && !$visibleToMember, 404);

        $data=$request->validate(['bids_page'=>['nullable','integer','min:1'],'bids_per_page'=>['nullable','integer','min:10','max:50']]);
        $auction->load(['item','winner.user:id,discord_id,discord_username,discord_display_name,discord_avatar','topBid.player.user:id,discord_id,discord_username,discord_display_name,discord_avatar']);
        $bids=$auction->bids()->with('player.user:id,discord_id,discord_username,discord_display_name,discord_avatar')->orderByDesc('amount')->orderBy('created_at')->orderBy('id')->paginate($data['bids_per_page']??20,['*'],'bids_page',$data['bids_page']??1);
        return response()->json($auction->toArray()+['bids'=>$bids->items(),'bids_meta'=>['current_page'=>$bids->currentPage(),'last_page'=>$bids->lastPage(),'total'=>$bids->total()]]);
    }
    public function store(Request $request,AuditService $audit)
    {
        abort_unless($request->user()->canCreateAuctions(),403);
        $data=$request->validate(['treasury_item_id'=>['required','integer','exists:treasury_items,id'],'quantity'=>['required','integer','min:1'],'starting_bid'=>['required','integer','min:0'],'minimum_step'=>['required','integer','min:1'],'extension_minutes'=>['sometimes','integer','min:2','max:5'],'ends_at'=>['required','date','after_or_equal:'.now()->addMinutes(10)->toISOString()]],['ends_at.after_or_equal'=>'Аукцион должен длиться не менее 10 минут.']);$data['extension_minutes']??=3;
        return DB::transaction(function()use($request,$data,$audit){$item=TreasuryItem::query()->lockForUpdate()->findOrFail($data['treasury_item_id']);if($data['quantity']>$item->available_quantity)throw ValidationException::withMessages(['quantity'=>'Недостаточно свободного количества в казне.']);$auction=Auction::query()->create($data+['status'=>'draft','created_by'=>$request->user()->id]);$audit->record('auction.created',$auction,null,$auction->getAttributes());return response()->json($auction->load('item'),201);});
    }
    public function update(Request $request,Auction $auction,AuditService $audit)
    {
        abort_unless($request->user()->canCreateAuctions(),403);
        $data=$request->validate(['treasury_item_id'=>['required','integer','exists:treasury_items,id'],'quantity'=>['required','integer','min:1'],'starting_bid'=>['required','integer','min:0'],'minimum_step'=>['required','integer','min:1'],'extension_minutes'=>['sometimes','integer','min:2','max:5'],'ends_at'=>['required','date','after_or_equal:'.now()->addMinutes(10)->toISOString()]],['ends_at.after_or_equal'=>'Аукцион должен длиться не менее 10 минут.']);$data['extension_minutes']??=$auction->extension_minutes;
        return DB::transaction(function()use($auction,$data,$audit){$locked=Auction::query()->lockForUpdate()->findOrFail($auction->id);if($locked->status!=='draft')throw ValidationException::withMessages(['auction'=>'Редактировать можно только черновик.']);$item=TreasuryItem::query()->lockForUpdate()->findOrFail($data['treasury_item_id']);if($data['quantity']>$item->available_quantity)throw ValidationException::withMessages(['quantity'=>'Недостаточно свободного количества в казне.']);$old=$locked->only(array_keys($data));$locked->update($data);$audit->record('auction.updated',$locked,$old,$data);return $locked->refresh()->load('item');});
    }
    public function start(Request $request,Auction $auction,AuditService $audit,ArmoryNotificationService $notifications)
    {
        abort_unless($request->user()->canCreateAuctions(),403);
        return DB::transaction(function()use($auction,$audit,$notifications){$locked=Auction::query()->lockForUpdate()->findOrFail($auction->id);if($locked->status!=='draft')throw ValidationException::withMessages(['auction'=>'Запустить можно только черновик.']);if($locked->ends_at->lt(now()->addMinutes(10)))throw ValidationException::withMessages(['ends_at'=>'До завершения аукциона должно оставаться не менее 10 минут.']);$tokenUnitValue=(int)(DB::table('treasury_token_settings')->where('id',1)->lockForUpdate()->value('token_unit_value')??0);if($tokenUnitValue<=0)throw ValidationException::withMessages(['auction'=>'Сначала настройте стоимость жетона.']);$item=TreasuryItem::query()->lockForUpdate()->findOrFail($locked->treasury_item_id);if($locked->quantity>$item->available_quantity)throw ValidationException::withMessages(['quantity'=>'Недостаточно свободного количества в казне.']);$item->increment('reserved_quantity',$locked->quantity);$locked->update(['status'=>'active','token_unit_value_snapshot'=>$tokenUnitValue]);$audit->record('auction.started',$locked,['status'=>'draft'],['status'=>'active','token_unit_value_snapshot'=>$tokenUnitValue]);DB::afterCommit(function()use($locked,$item,$notifications){SendDiscordNotification::dispatch('Новый аукцион','Выставлен лот «'.$item->item_name.'» ×'.$locked->quantity.'. Стартовая ставка: **'.number_format($locked->starting_bid,0,'',' ').' жетонов**. Завершение: '.$locked->ends_at->format('d.m.Y H:i').'.');$notifications->notify($notifications->activeMembers(),'auction_started','Аукцион начался','Лот «'.$item->item_name.'» ×'.$locked->quantity.' открыт для ставок.','/auctions/'.$locked->id,'auction-started-'.$locked->id);});return $locked->refresh()->load('item');});
    }
    public function bid(Request $request,Auction $auction,PlaceAuctionBid $action){$player=$request->user()->player;abort_unless($player&&$player->is_active,403,'Привяжите активного персонажа.');$amount=$request->validate(['amount'=>['required','integer','min:0']])['amount'];return response()->json($action->execute($auction,$player,$amount),201);}
    public function finish(Request $request,Auction $auction,FinishAuction $action){abort_unless($request->user()->canCreateAuctions(),403);return $action->execute($auction,$request->user()->id);}
    public function cancel(Request $request,Auction $auction,AuditService $audit){abort_unless($request->user()->canCreateAuctions(),403);return DB::transaction(function()use($auction,$audit){$locked=Auction::query()->lockForUpdate()->findOrFail($auction->id);if(!in_array($locked->status,['draft','active'],true))throw ValidationException::withMessages(['auction'=>'Этот лот уже завершён.']);if($locked->status==='active'){TreasuryItem::query()->lockForUpdate()->findOrFail($locked->treasury_item_id)->decrement('reserved_quantity',$locked->quantity);}$old=$locked->status;$locked->update(['status'=>'cancelled','finished_at'=>now()]);$audit->record('auction.cancelled',$locked,['status'=>$old],['status'=>'cancelled']);return $locked->refresh()->load('item');});}
    public function archive(Request $request){return response()->json(['players'=>Auction::query()->where('status','finished')->whereNotNull('winner_player_id')->with('winner:id,nickname')->selectRaw('winner_player_id, COUNT(*) AS wins, SUM(winning_bid) AS spent')->groupBy('winner_player_id')->orderByDesc('wins')->get()->map(fn($row)=>['player_id'=>$row->winner_player_id,'nickname'=>$row->winner?->nickname,'wins'=>(int)$row->wins,'spent'=>(int)$row->spent]),'lots'=>Auction::query()->where('status','finished')->with(['item:id,item_name,icon_path','winner:id,nickname'])->orderByDesc('finished_at')->limit(200)->get()]);}
}
