<?php
namespace App\Http\Controllers;
use App\Models\Auction;
use App\Models\TreasuryItem;
use App\Services\AuditService;
use App\Actions\PlaceAuctionBid;
use App\Actions\FinishAuction;
use App\Jobs\SendDiscordNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
final class AuctionController extends Controller
{
    public function index(Request $request){return Auction::query()->with(['item','winner:id,nickname','topBid.player:id,nickname'])->withCount('bids')->when($request->input('status'),fn($q,$v)=>$q->where('status',$v))->orderByDesc('id')->get();}
    public function show(Auction $auction){return $auction->load(['item','winner:id,nickname','bids'=>fn($q)=>$q->with('player:id,nickname')->orderByDesc('amount')->orderBy('created_at')->orderBy('id')]);}
    public function store(Request $request,AuditService $audit)
    {
        abort_unless($request->user()->canManageGuild(),403);
        $data=$request->validate(['treasury_item_id'=>['required','integer','exists:treasury_items,id'],'quantity'=>['required','integer','min:1'],'starting_bid'=>['required','integer','min:0'],'minimum_step'=>['required','integer','min:1'],'ends_at'=>['required','date','after:now']],['ends_at.after'=>'Время завершения должно быть позже текущего времени.']);
        $item=TreasuryItem::query()->findOrFail($data['treasury_item_id']);
        if($data['quantity']>$item->available_quantity)throw ValidationException::withMessages(['quantity'=>'Недостаточно свободного количества в казне.']);
        $auction=Auction::query()->create($data+['status'=>'draft','created_by'=>$request->user()->id]);$audit->record('auction.created',$auction,null,$auction->getAttributes());return response()->json($auction->load('item'),201);
    }
    public function update(Request $request,Auction $auction,AuditService $audit)
    {
        abort_unless($request->user()->canManageGuild(),403);
        $data=$request->validate(['treasury_item_id'=>['required','integer','exists:treasury_items,id'],'quantity'=>['required','integer','min:1'],'starting_bid'=>['required','integer','min:0'],'minimum_step'=>['required','integer','min:1'],'ends_at'=>['required','date','after:now']],['ends_at.after'=>'Время завершения должно быть позже текущего времени.']);
        return DB::transaction(function()use($auction,$data,$audit){$locked=Auction::query()->lockForUpdate()->findOrFail($auction->id);if($locked->status!=='draft')throw ValidationException::withMessages(['auction'=>'Редактировать можно только черновик.']);$item=TreasuryItem::query()->lockForUpdate()->findOrFail($data['treasury_item_id']);if($data['quantity']>$item->available_quantity)throw ValidationException::withMessages(['quantity'=>'Недостаточно свободного количества в казне.']);$old=$locked->only(array_keys($data));$locked->update($data);$audit->record('auction.updated',$locked,$old,$data);return $locked->refresh()->load('item');});
    }
    public function start(Request $request,Auction $auction,AuditService $audit)
    {
        abort_unless($request->user()->canManageGuild(),403);
        return DB::transaction(function()use($auction,$audit){$locked=Auction::query()->lockForUpdate()->findOrFail($auction->id);if($locked->status!=='draft')throw ValidationException::withMessages(['auction'=>'Запустить можно только черновик.']);if($locked->ends_at->isPast())throw ValidationException::withMessages(['ends_at'=>'Укажите новое время завершения.']);$item=TreasuryItem::query()->lockForUpdate()->findOrFail($locked->treasury_item_id);if($locked->quantity>$item->available_quantity)throw ValidationException::withMessages(['quantity'=>'Недостаточно свободного количества в казне.']);$item->increment('reserved_quantity',$locked->quantity);$locked->update(['status'=>'active']);$audit->record('auction.started',$locked,['status'=>'draft'],['status'=>'active']);DB::afterCommit(fn()=>SendDiscordNotification::dispatch('Новый аукцион', 'Выставлен лот «'.$item->item_name.'» ×'.$locked->quantity.'. Стартовая ставка: **'.number_format($locked->starting_bid,0,'',' ').' золота**. Завершение: '.$locked->ends_at->format('d.m.Y H:i').'.'));return $locked->refresh()->load('item');});
    }
    public function bid(Request $request,Auction $auction,PlaceAuctionBid $action){$player=$request->user()->player;abort_unless($player&&$player->is_active,403,'Привяжите активного персонажа.');$amount=$request->validate(['amount'=>['required','integer','min:0']])['amount'];return response()->json($action->execute($auction,$player,$amount),201);}
    public function finish(Request $request,Auction $auction,FinishAuction $action){abort_unless($request->user()->canManageGuild(),403);return $action->execute($auction,$request->user()->id);}
    public function cancel(Request $request,Auction $auction,AuditService $audit){abort_unless($request->user()->canManageGuild(),403);return DB::transaction(function()use($auction,$audit){$locked=Auction::query()->lockForUpdate()->findOrFail($auction->id);if(!in_array($locked->status,['draft','active'],true))throw ValidationException::withMessages(['auction'=>'Этот лот уже завершён.']);if($locked->status==='active'){TreasuryItem::query()->lockForUpdate()->findOrFail($locked->treasury_item_id)->decrement('reserved_quantity',$locked->quantity);}$old=$locked->status;$locked->update(['status'=>'cancelled','finished_at'=>now()]);$audit->record('auction.cancelled',$locked,['status'=>$old],['status'=>'cancelled']);return $locked->refresh()->load('item');});}
}
