<?php

namespace App\Http\Controllers;

use App\Models\TreasuryItem;
use App\Models\TreasuryItemTransaction;
use App\Models\TreasuryTransaction;
use Illuminate\Support\Facades\DB;

final class TreasuryController extends Controller
{
    public function __invoke(): array
    {
        $items = TreasuryItem::query()->where('quantity', '>', 0)->orderBy('item_name')->get();
        $transactionQuery = TreasuryItemTransaction::query()->with([
            'item:id,item_name,icon_path',
            'recipient:id,nickname',
            'sourceActivity.definition:id,name,type',
            'creator:id,discord_username,discord_display_name',
        ]);

        return [
            'gold' => (int) (DB::table('treasury_transactions')->latest('id')->value('balance_after') ?? 0),
            'inventory_value' => (int) $items->sum(fn ($item) => $item->quantity * $item->unit_value),
            'items' => $items,
            'recent_drops' => (clone $transactionQuery)->where('quantity_delta', '>', 0)->latest('id')->limit(5)->get(),
            'transactions' => $transactionQuery->latest('id')->limit(50)->get(),
            'gold_transactions' => TreasuryTransaction::query()
                ->with('creator:id,discord_username,discord_display_name')
                ->latest('id')
                ->limit(50)
                ->get(),
        ];
    }
}
