<?php

namespace App\Http\Controllers;

use App\Actions\AddTreasuryItem;
use App\Enums\UserRole;
use App\Models\LootCatalogItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TreasuryItemAddController extends Controller
{
    public function __invoke(Request $request, AddTreasuryItem $action): JsonResponse
    {
        abort_unless($request->user()->hasRole(UserRole::Developer), 403);

        $data = $request->validate([
            'loot_catalog_item_id' => ['required', 'integer', 'exists:loot_catalog_items,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_value' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $catalogItem = LootCatalogItem::query()
            ->where('is_active', true)
            ->findOrFail($data['loot_catalog_item_id']);

        $transaction = $action->execute(
            $catalogItem,
            (int) $data['quantity'],
            (int) $data['unit_value'],
            $data['reason'],
            $request->user()->id,
        );

        return response()->json($transaction, 201);
    }
}
