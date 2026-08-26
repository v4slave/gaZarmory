<?php
namespace App\Http\Controllers;
use App\Models\ArmoryNotification;use Illuminate\Http\Request;
final class NotificationController extends Controller
{
 public function index(Request $request){$retentionDays=max(1,(int)config('notifications.retention_days',7));$query=ArmoryNotification::query()->where('user_id',$request->user()->id)->where('created_at','>=',now()->subDays($retentionDays));return response()->json(['unread_count'=>(clone $query)->whereNull('read_at')->count(),'items'=>$query->latest()->limit(50)->get()]);}
 public function read(Request $request,ArmoryNotification $notification){abort_unless($notification->user_id===$request->user()->id,404);$notification->update(['read_at'=>$notification->read_at??now()]);return $notification;}
 public function readAll(Request $request){ArmoryNotification::query()->where('user_id',$request->user()->id)->whereNull('read_at')->update(['read_at'=>now()]);return response()->noContent();}
}
