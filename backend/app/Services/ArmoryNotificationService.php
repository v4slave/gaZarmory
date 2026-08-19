<?php
namespace App\Services;
use App\Models\ArmoryNotification;use App\Models\User;use Illuminate\Support\Collection;
final class ArmoryNotificationService
{
 public function notify(User|Collection|array $recipients,string $type,string $title,string $message,?string $url=null,?string $dedupeKey=null):void{$users=$recipients instanceof User?collect([$recipients]):collect($recipients);foreach($users->unique('id') as $user){$values=['type'=>$type,'data'=>compact('title','message','url'),'read_at'=>null];if($dedupeKey)ArmoryNotification::query()->firstOrCreate(['user_id'=>$user->id,'dedupe_key'=>$dedupeKey],$values);else ArmoryNotification::query()->create($values+['user_id'=>$user->id]);}}
 public function administrators():Collection{return User::query()->get()->filter(fn(User $user)=>$user->canAdministrate())->values();}
 public function financialLeaders():Collection{return User::query()->get()->filter(fn(User $user)=>$user->canHandleTreasuryItems())->values();}
 public function activeMembers():Collection{return User::query()->whereHas('player',fn($q)=>$q->where('is_active',true))->get();}
}
