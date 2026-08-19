<?php
namespace App\Http\Controllers;
use App\Enums\PlayerClass;
use App\Models\Player;
use App\Models\PlayerLinkRequest;
use App\Rules\ValidPlayerNickname;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class SelfPlayerController extends Controller
{
    public function options()
    {
        return Player::query()
            ->where('is_active', true)
            ->whereNull('user_id')
            ->orderBy('nickname')
            ->get(['id', 'nickname', 'class']);
    }

    public function link(Request $request)
    {
        $data=$request->validate(['player_id'=>['required','integer','exists:players,id']]);
        if($request->user()->player()->exists()) throw ValidationException::withMessages(['player_id'=>'Ваш Discord уже привязан к игровому профилю.']);
        if(PlayerLinkRequest::query()->where('user_id',$request->user()->id)->where('status','pending')->exists()) throw ValidationException::withMessages(['player_id'=>'У вас уже есть заявка на рассмотрении.']);
        $player=Player::query()->findOrFail($data['player_id']);
        if(!$player->is_active) throw ValidationException::withMessages(['player_id'=>'Нельзя привязать деактивированный профиль.']);
        if($player->user_id!==null) throw ValidationException::withMessages(['player_id'=>'Этот профиль уже занят.']);
        if(PlayerLinkRequest::query()->where('player_id',$player->id)->where('status','pending')->exists()) throw ValidationException::withMessages(['player_id'=>'На этот профиль уже подана заявка.']);
        return response()->json(PlayerLinkRequest::query()->create(['user_id'=>$request->user()->id,'player_id'=>$player->id,'status'=>'pending'])->load('player:id,nickname,class'),201);
    }

    public function rename(Request $request,AuditService $audit)
    {
        $player=$request->user()->player;
        abort_unless($player,404,'Игровой профиль не привязан.');
        $data=$request->validate(['nickname'=>['required','string',new ValidPlayerNickname(),Rule::unique('players','nickname')->ignore($player)]]);
        $old=['nickname'=>$player->nickname]; $player->update($data); $audit->record('player.self_renamed',$player,$old,$data);
        return $player->refresh()->load(['group','user']);
    }

    public function changeClass(Request $request, AuditService $audit)
    {
        $player = $request->user()->player;
        abort_unless($player, 404, 'Игровой профиль не привязан.');

        $data = $request->validate(['class' => ['required', Rule::enum(PlayerClass::class)]]);
        $old = ['class' => $player->class];
        $player->update($data);
        $audit->record('player.self_class_changed', $player, $old, $data);

        return $player->refresh()->load(['group', 'user']);
    }

    public function updateProfile(Request $request, AuditService $audit)
    {
        $player = $request->user()->player;
        abort_unless($player, 404, 'Игровой профиль не привязан.');
        $fields = ['has_ship','has_tank','has_fuchsias','has_clouds','has_machaon','has_tare','has_deer','has_invulnerable_pet','has_shield_swap','has_flippers'];
        $rules = ['gear_score' => ['required', 'integer', 'min:0', 'max:100000']];
        foreach ($fields as $field) $rules[$field] = ['required', 'boolean'];
        $data = $request->validate($rules);
        if ((int) $data['gear_score'] !== (int) $player->gear_score) {
            $data['previous_gear_score'] = $player->gear_score;
            $data['gear_score_updated_at'] = now();
        }
        $old = $player->only(array_keys($data));
        $player->update($data);
        $audit->record('player.self_profile_updated', $player, $old, $data);
        return $player->refresh()->load(['group', 'user']);
    }
}
