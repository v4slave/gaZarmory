<?php

namespace App\Actions;

use App\Models\LootCatalogItem;
use App\Models\TreasuryItem;
use App\Models\TreasuryItemTransaction;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

final class AddTreasuryItem
{
    public function __construct(private readonly AuditService $audit) {}

    public function execute(
        LootCatalogItem $catalogItem,
        int $quantity,
        int $unitValue,
        string $reason,
        int $userId,
    ): TreasuryItemTransaction {
        return DB::transaction(function () use ($catalogItem, $quantity, $unitValue, $reason, $userId): TreasuryItemTransaction {
            $item = TreasuryItem::query()
                ->where('item_name', $catalogItem->name)
                ->lockForUpdate()
                ->first();

            if (! $item) {
                $item = TreasuryItem::query()->create([
                    'item_name' => $catalogItem->name,
                    'quantity' => 0,
                    'reserved_quantity' => 0,
                    'unit_value' => $unitValue,
                    'icon_path' => $catalogItem->icon_path,
                    'rarity' => $catalogItem->rarity,
                ]);
            }

            $item->quantity += $quantity;
            $item->unit_value = $unitValue;
            $item->icon_path = $catalogItem->icon_path;
            $item->rarity = $catalogItem->rarity;
            $item->save();

            $transaction = TreasuryItemTransaction::query()->create([
                'treasury_item_id' => $item->id,
                'type' => 'adjustment',
                'quantity_delta' => $quantity,
                'source_activity_id' => null,
                'reason' => $reason,
                'created_by' => $userId,
            ]);

            $this->audit->record('treasury_item.added', $transaction, null, [
                'treasury_item_id' => $item->id,
                'quantity' => $quantity,
                'unit_value' => $unitValue,
                'reason' => $reason,
            ]);

            return $transaction->load('item');
        });
    }
}
