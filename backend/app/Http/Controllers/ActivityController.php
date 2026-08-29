<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityDefinition;
use App\Models\TreasuryItem;
use App\Models\TreasuryItemTransaction;
use App\Actions\CalculatePrimeShares;
use App\Services\AuditService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ActivityController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Activity::class);
        $data = $request->validate(['date_from'=>['nullable','date'],'date_to'=>['nullable','date'],'definition_id'=>['nullable','integer'],'player_id'=>['nullable','integer'],'per_page'=>['nullable','integer','min:10','max:100']]);
        return Activity::query()->with('definition:id,name,type,icon_path')->withCount(['players','earnings'])
            ->whereHas('definition', fn($definition) => $definition->where('type', 'prime'))
            ->when($data['date_from']??null, fn($q,$v)=>$q->whereDate('occurred_at','>=',$v))
            ->when($data['date_to']??null, fn($q,$v)=>$q->whereDate('occurred_at','<=',$v))
            ->when($data['definition_id']??null, fn($q,$v)=>$q->where('activity_definition_id',$v))
            ->when($data['player_id']??null, fn($q,$v)=>$q->whereHas('players',fn($p)=>$p->whereKey($v)))
            ->orderByDesc('occurred_at')->paginate($data['per_page']??25);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Activity::class);
        $data=$request->validate(['activity_definition_id'=>['required','exists:activity_definitions,id'],'occurred_at'=>['required','date'],'gold_value'=>['nullable','integer','min:0']]);
        $data['occurred_at']=CarbonImmutable::parse($data['occurred_at'])->setTimezone(config('app.timezone'));
        $definition=ActivityDefinition::query()->findOrFail($data['activity_definition_id']);
        abort_unless($definition->type->value === 'prime', 422, __('domain.activity.prime_only'));
        if($definition->type->value !== 'prime' && array_key_exists('gold_value',$data) && $data['gold_value']!==null) throw ValidationException::withMessages(['gold_value'=>__('domain.activity.gold_prime_only')]);
        $activity=Activity::query()->create($data+['created_by'=>$request->user()->id]);
        $this->audit->record('activity.created',$activity,null,$activity->getAttributes());
        return response()->json($activity->load('definition'),201);
    }

    public function show(Activity $activity) { $this->authorize('view',$activity); return $activity->load(['definition','players.group','loot','earnings','lootImports.rows']); }

    public function update(Request $request, Activity $activity)
    {
        $this->authorize('update',$activity);
        abort_if($activity->completed_at,409,__('domain.activity.completed_locked'));
        abort_if($activity->earnings()->exists(),409,__('domain.activity.earnings_locked'));
        $data=$request->validate(['occurred_at'=>['sometimes','date'],'gold_value'=>['sometimes','nullable','integer','min:0']]);
        if(isset($data['occurred_at']))$data['occurred_at']=CarbonImmutable::parse($data['occurred_at'])->setTimezone(config('app.timezone'));
        $old=$activity->only(array_keys($data)); $activity->update($data); $this->audit->record('activity.updated',$activity,$old,$data);
        return $activity->refresh()->load('definition');
    }

    public function destroy(Request $request, Activity $activity)
    {
        $this->authorize('delete',$activity);
        DB::transaction(function () use ($activity, $request): void {
            $locked = Activity::query()->with('loot')->lockForUpdate()->findOrFail($activity->id);
            abort_if($locked->completed_at || $locked->earnings()->exists(), 409, __('domain.activity.delete_draft_only'));
            foreach ($locked->loot as $loot) {
                $item = TreasuryItem::query()->where('item_name', $loot->item_name)->lockForUpdate()->first();
                if (!$item || $item->available_quantity < $loot->quantity) {
                    throw ValidationException::withMessages(['activity' => __('domain.activity.loot_already_used', ['item' => $loot->item_name])]);
                }
                $item->decrement('quantity', $loot->quantity);
                TreasuryItemTransaction::query()->create(['treasury_item_id'=>$item->id,'type'=>'adjustment','quantity_delta'=>-$loot->quantity,'source_activity_id'=>$locked->id,'reason'=>'Удаление черновика активности','created_by'=>$request->user()->id]);
            }
            $old = $locked->getAttributes();
            $this->audit->record('activity.deleted', $locked, $old, null);
            $locked->lootImports()->delete();
            $locked->loot()->delete();
            $locked->players()->detach();
            TreasuryItemTransaction::query()->where('source_activity_id', $locked->id)->update(['source_activity_id'=>null]);
            $locked->delete();
        });
        return response()->noContent();
    }

    public function addPlayers(Request $request, Activity $activity)
    {
        $this->authorize('update',$activity);
        abort_if($activity->completed_at,409,__('domain.activity.completed_locked'));
        $ids=$request->validate(['player_ids'=>['required','array','min:1'],'player_ids.*'=>['integer','distinct','exists:players,id']])['player_ids'];
        DB::transaction(function()use($activity,$ids){$locked=Activity::query()->lockForUpdate()->findOrFail($activity->id);abort_if($locked->completed_at,409,__('domain.activity.completed_locked'));abort_if($locked->earnings()->exists(),409,__('domain.activity.participants_locked'));$locked->players()->syncWithoutDetaching($ids);});
        return $activity->load('players');
    }

    public function removePlayer(Activity $activity, int $playerId)
    {
        $this->authorize('update',$activity);
        abort_if($activity->completed_at,409,__('domain.activity.completed_locked'));
        DB::transaction(function()use($activity,$playerId){$locked=Activity::query()->lockForUpdate()->findOrFail($activity->id);abort_if($locked->completed_at,409,__('domain.activity.completed_locked'));abort_if($locked->earnings()->exists(),409,__('domain.activity.participants_locked'));$locked->players()->detach($playerId);});return response()->noContent();
    }

    public function complete(Request $request, Activity $activity, CalculatePrimeShares $action)
    {
        $this->authorize('update', $activity);
        $activity->load('definition');
        abort_if($activity->definition->type->value !== 'mini_activity', 422, __('domain.activity.complete_mini_only'));
        abort_if($activity->completed_at && $activity->earnings()->exists(), 409, __('domain.activity.mini_already_completed'));
        abort_if($activity->lootImports()->where('status', 'draft')->exists(), 422, __('domain.activity.confirm_loot_import_first'));

        return $action->execute($activity);
    }

    public function reopen(Request $request, Activity $activity)
    {
        $this->authorize('update', $activity);
        $data = $request->validate(['reason' => ['required','string','min:10','max:1000']]);

        return DB::transaction(function () use ($request, $activity, $data): Activity {
            $locked = Activity::query()->lockForUpdate()->findOrFail($activity->id);
            $earnings = $locked->earnings()->lockForUpdate()->get();
            abort_if($earnings->isEmpty(), 409, __('domain.activity.no_earnings_to_cancel'));
            abort_if($earnings->contains(fn ($earning) => $earning->status !== 'pending'), 409, __('domain.activity.earnings_not_pending'));
            abort_if($earnings->contains(fn ($earning) => $earning->payout_id !== null), 409, __('domain.activity.earnings_in_payout'));

            $snapshot = [
                'reason' => $data['reason'],
                'earnings_count' => $earnings->count(),
                'earnings_total' => (int) $earnings->sum('player_share'),
                'earning_ids' => $earnings->modelKeys(),
                'previous_gold_value' => $locked->gold_value,
                'previous_completed_at' => $locked->completed_at?->toISOString(),
            ];
            $earnings->each->delete();
            $locked->update(['completed_at' => null, 'gold_value' => null]);
            $this->audit->record('activity.reopened_for_correction', $locked, $snapshot, [
                'reason' => $data['reason'],
                'status' => 'editing',
                'author_id' => $request->user()->id,
            ]);

            return $locked->refresh()->load(['definition','players.group','loot','earnings','lootImports.rows']);
        });
    }
}
