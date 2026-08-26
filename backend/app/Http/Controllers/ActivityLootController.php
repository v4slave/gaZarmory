<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityLoot;
use App\Models\TreasuryItem;
use App\Models\TreasuryItemTransaction;
use App\Models\LootCatalogItem;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ActivityLootController extends Controller
{
    public function store(Request $request, Activity $activity, AuditService $audit): JsonResponse
    {
        abort_unless($request->user()->canManageGuild(), 403);
        abort_if($activity->completed_at, 409, 'Завершённая активность immutable.');
        abort_if($activity->earnings()->exists(), 409, 'Лут рассчитанного прайма immutable.');
        $data = $request->validate([
            'loot_catalog_item_id' => ['required', 'integer', 'exists:loot_catalog_items,id'],
            'unit_price' => ['required', 'integer', 'min:0'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);
        $catalogItem = LootCatalogItem::query()->where('is_active',true)->findOrFail($data['loot_catalog_item_id']);
        $iconPath = $catalogItem->icon_path;

        $loot = DB::transaction(function () use ($activity, $data, $catalogItem, $iconPath, $request, $audit): ActivityLoot {
            $lockedActivity = Activity::query()->lockForUpdate()->findOrFail($activity->id);
            abort_if($lockedActivity->completed_at, 409, 'Завершённая активность immutable.');
            abort_if($lockedActivity->earnings()->exists(), 409, 'Лут рассчитанного прайма immutable.');
            $loot = ActivityLoot::query()->create([
                'activity_id' => $lockedActivity->id,
                'loot_catalog_item_id' => $catalogItem->id,
                'item_name' => $catalogItem->name,
                'unit_price' => $data['unit_price'],
                'quantity' => $data['quantity'],
                'icon_path' => $iconPath,
                'rarity' => $catalogItem->rarity,
                'created_by' => $request->user()->id,
            ]);
            $item = TreasuryItem::query()->lockForUpdate()->firstOrCreate(
                ['item_name' => $catalogItem->name],
                ['quantity' => 0, 'reserved_quantity' => 0, 'unit_value' => $data['unit_price'], 'icon_path' => $iconPath, 'rarity' => $catalogItem->rarity]
            );
            $item->quantity += $data['quantity'];
            $item->unit_value = $data['unit_price'];
            if ($iconPath) $item->icon_path = $iconPath;
            $item->rarity = $catalogItem->rarity;
            $item->save();
            TreasuryItemTransaction::query()->create([
                'treasury_item_id' => $item->id,
                'type' => 'loot_income',
                'quantity_delta' => $data['quantity'],
                'source_activity_id' => $lockedActivity->id,
                'reason' => 'Ручное добавление лута',
                'created_by' => $request->user()->id,
            ]);
            $audit->record('activity_loot.created', $loot, null, $loot->getAttributes());
            return $loot;
        });

        return response()->json($loot->refresh(), 201);
    }

    public function destroy(Request $request, Activity $activity, ActivityLoot $loot, AuditService $audit): JsonResponse
    {
        abort_unless($request->user()->canManageGuild(), 403);
        abort_unless($loot->activity_id === $activity->id, 404);

        DB::transaction(function () use ($activity, $loot, $request, $audit): void {
            $lockedActivity = Activity::query()->lockForUpdate()->findOrFail($activity->id);
            abort_if($lockedActivity->completed_at, 409, 'Из завершённой активности нельзя удалить лут.');
            abort_if($lockedActivity->earnings()->exists(), 409, 'Лут рассчитанного прайма нельзя изменить.');
            $lockedLoot = ActivityLoot::query()->lockForUpdate()->findOrFail($loot->id);
            $item = TreasuryItem::query()->where('item_name', $lockedLoot->item_name)->lockForUpdate()->first();
            if (!$item || $item->available_quantity < $lockedLoot->quantity) {
                throw ValidationException::withMessages(['loot' => 'Предмет уже зарезервирован, продан или выдан. Сначала отмените связанную операцию.']);
            }
            $item->decrement('quantity', $lockedLoot->quantity);
            TreasuryItemTransaction::query()->create([
                'treasury_item_id' => $item->id,
                'type' => 'adjustment',
                'quantity_delta' => -$lockedLoot->quantity,
                'source_activity_id' => $lockedActivity->id,
                'reason' => 'Удаление лута из черновика активности',
                'created_by' => $request->user()->id,
            ]);
            $old = $lockedLoot->getAttributes();
            $audit->record('activity_loot.deleted', $lockedLoot, $old, null);
            $lockedLoot->delete();
        });

        return response()->json(null, 204);
    }

    public function update(Request $request, Activity $activity, ActivityLoot $loot, AuditService $audit): ActivityLoot
    {
        abort_unless($request->user()->canManageGuild(), 403);
        abort_unless($loot->activity_id === $activity->id, 404);
        $data = $request->validate(['unit_price' => ['required','integer','min:0']]);

        return DB::transaction(function () use ($activity, $loot, $data, $audit): ActivityLoot {
            $lockedActivity = Activity::query()->lockForUpdate()->findOrFail($activity->id);
            abort_if($lockedActivity->completed_at, 409, 'Сначала откройте активность для исправления.');
            abort_if($lockedActivity->earnings()->exists(), 409, 'Сначала отмените рассчитанные начисления.');
            $lockedLoot = ActivityLoot::query()->lockForUpdate()->findOrFail($loot->id);
            $old = ['unit_price' => $lockedLoot->unit_price];
            $lockedLoot->update($data);
            TreasuryItem::query()->where('item_name', $lockedLoot->item_name)->update(['unit_value' => $data['unit_price']]);
            $audit->record('activity_loot.price_corrected', $lockedLoot, $old, $data);
            return $lockedLoot->refresh();
        });
    }
}
