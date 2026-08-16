<?php
namespace App\Http\Controllers;
use App\Models\PrimePlayerEarning;
use App\Models\PayoutPlayer;
use Illuminate\Http\Request;
final class PendingEarningController extends Controller
{
    public function __invoke(Request $request):array
    {
        $query=PrimePlayerEarning::query()->where('status','pending');
        if(!$request->user()->canManageGuild()){abort_unless($request->user()->player,403);$query->where('player_id',$request->user()->player->id);}
        $rows=(clone $query)
            ->selectRaw("player_id, MAX(nickname_snapshot) AS nickname, COUNT(*) FILTER (WHERE activity_definitions.type = 'prime') AS primes_count, COUNT(*) FILTER (WHERE activity_definitions.type = 'mini_activity') AS mini_activities_count, SUM(player_share) AS amount")
            ->join('activities','activities.id','=','prime_player_earnings.activity_id')
            ->join('activity_definitions','activity_definitions.id','=','activities.activity_definition_id')
            ->groupBy('player_id')->orderByDesc('amount')->get();
        $primes=(clone $query)->whereHas('activity.definition',fn($q)=>$q->where('type','prime'))->distinct('activity_id')->count('activity_id');
        $mini=(clone $query)->whereHas('activity.definition',fn($q)=>$q->where('type','mini_activity'))->distinct('activity_id')->count('activity_id');
        $paid=PayoutPlayer::query()->where('status','paid');
        if(!$request->user()->canManageGuild()){$paid->where('player_id',$request->user()->player->id);}
        return ['summary'=>['gold'=>(int)$rows->sum('amount'),'primes'=>(int)$primes,'mini_activities'=>(int)$mini,'participants'=>$rows->count(),'paid_gold'=>(int)$paid->sum('amount')],'players'=>$rows];
    }
}
