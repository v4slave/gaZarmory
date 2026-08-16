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
                'created_by' => $request->user()->id,
            ]);
            $item = TreasuryItem::query()->lockForUpdate()->firstOrCreate(
                ['item_name' => $catalogItem->name],
                ['quantity' => 0, 'reserved_quantity' => 0, 'unit_value' => $data['unit_price'], 'icon_path' => $iconPath]
            );
            $item->quantity += $data['quantity'];
            $item->unit_value = $data['unit_price'];
            if ($iconPath) $item->icon_path = $iconPath;
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
}
