<?php
namespace App\Http\Controllers;
use App\Actions\CalculatePayout;
use App\Actions\CompletePayout;
use App\Models\Payout;
use App\Models\PrimePlayerEarning;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
final class PayoutController extends Controller
{
    public function index(Request $request){$q=Payout::query()->with(['players','activities.definition:id,name,type'])->orderByDesc('id');if(!$request->user()->canManageGuild()){abort_unless($request->user()->player,403);$q->whereHas('players',fn($p)=>$p->where('player_id',$request->user()->player->id));}return $q->get();}
    public function show(Request $request,Payout $payout){if(!$request->user()->canManageGuild()){abort_unless($request->user()->player&&$payout->players()->where('player_id',$request->user()->player->id)->exists(),403);}return $payout->load(['creator:id,discord_username,discord_display_name','players'=>fn($q)=>$q->orderByDesc('amount')->orderBy('nickname_snapshot'),'activities'=>fn($q)=>$q->with('definition:id,name,type')->orderByDesc('occurred_at')]);}
    public function store(Request $request,AuditService $audit,CalculatePayout $calculate,CompletePayout $complete){abort_unless($request->user()->canCreatePayouts(),403);$data=$request->validate(['period_from'=>['required','date'],'period_to'=>['required','date','after_or_equal:period_from']]);return DB::transaction(function()use($request,$data,$audit,$calculate,$complete){$payout=Payout::query()->create($data+['status'=>'draft','created_by'=>$request->user()->id]);$audit->record('payout.created',$payout,null,$payout->getAttributes());$calculated=$calculate->execute($payout);$paid=$complete->execute($calculated,$request->user()->id);return response()->json($paid,201);});}
    public function calculate(Request $request,Payout $payout,CalculatePayout $action){abort_unless($request->user()->canManageGuild(),403);return $action->execute($payout);}
    public function complete(Request $request,Payout $payout,CompletePayout $action){abort_unless($request->user()->canManageGuild(),403);return $action->execute($payout,$request->user()->id);}
    public function cancel(Request $request,Payout $payout,AuditService $audit){abort_unless($request->user()->canManageGuild(),403);return DB::transaction(function()use($payout,$audit){$locked=Payout::query()->lockForUpdate()->findOrFail($payout->id);if(!in_array($locked->status,['draft','calculated'],true))throw ValidationException::withMessages(['payout'=>'Этот нахрюк нельзя отменить.']);PrimePlayerEarning::query()->where('payout_id',$locked->id)->where('status','pending')->update(['payout_id'=>null]);$locked->activities()->detach();$locked->players()->where('status','pending')->update(['status'=>'cancelled']);$old=$locked->status;$locked->update(['status'=>'cancelled']);$audit->record('payout.cancelled',$locked,['status'=>$old],['status'=>'cancelled']);return $locked->refresh()->load('players');});}
    public function destroy(Request $request,Payout $payout,AuditService $audit){abort_unless($request->user()->canManageGuild(),403);return DB::transaction(function()use($payout,$audit){$locked=Payout::query()->lockForUpdate()->findOrFail($payout->id);if($locked->status!=='draft')throw ValidationException::withMessages(['payout'=>'Удалить можно только черновик.']);if($locked->players()->exists()||$locked->activities()->exists())throw ValidationException::withMessages(['payout'=>'Черновик уже содержит рассчитанные данные и не может быть удалён.']);$old=$locked->getAttributes();$audit->record('payout.deleted',$locked,$old,null);$locked->delete();return response()->noContent();});}
}
