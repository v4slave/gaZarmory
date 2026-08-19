<?php

namespace App\Http\Controllers;

use App\Models\TreasuryItem;
use App\Models\TreasuryItemTransaction;
use App\Models\TreasuryTransaction;
use Illuminate\Support\Facades\DB;

final class TreasuryController extends Controller
{
    public function items()
    {
        return TreasuryItem::query()->where('quantity', '>', 0)->orderBy('item_name')->get();
    }

    public function __invoke(): array
    {
        $items = TreasuryItem::query()->where('quantity', '>', 0)->orderBy('item_name')->get();
        $tokenSettings = DB::table('treasury_token_settings')->where('id', 1)->first();
        $tokenUnitValue = (int) ($tokenSettings?->token_unit_value ?? 0);
        $gold = (int) (DB::table('treasury_transactions')->latest('id')->value('balance_after') ?? 0);
        $tokenCount = $tokenUnitValue > 0 ? intdiv($gold, $tokenUnitValue) : 0;
        $transactionQuery = TreasuryItemTransaction::query()->with([
            'item:id,item_name,icon_path,unit_value',
            'recipient:id,nickname',
            'sourceActivity:id,activity_definition_id,occurred_at',
            'sourceActivity.definition:id,name,type,icon_path',
            'creator:id,discord_username,discord_display_name',
        ]);

        $recentDrops = (clone $transactionQuery)
            ->where('quantity_delta', '>', 0)
            ->whereNotNull('source_activity_id')
            ->whereHas('sourceActivity.definition', fn ($definition) => $definition->where('type', 'prime'))
            ->latest('id')
            ->limit(150)
            ->get()
            ->groupBy('source_activity_id')
            ->take(6)
            ->map(function ($transactions) {
                $activity = $transactions->first()->sourceActivity;

                return [
                    'id' => $activity->id,
                    'name' => $activity->definition->name,
                    'type' => $activity->definition->type->value,
                    'occurred_at' => $activity->occurred_at,
                    'icon_url' => $activity->definition->icon_url,
                    'total_value' => (int) $transactions->sum(
                        fn ($transaction) => $transaction->quantity_delta * ($transaction->item?->unit_value ?? 0)
                    ),
                    'items' => $transactions->map(fn ($transaction) => [
                        'id' => $transaction->id,
                        'name' => $transaction->item?->item_name,
                        'icon_url' => $transaction->item?->icon_url,
                        'quantity' => $transaction->quantity_delta,
                    ])->values(),
                ];
            })
            ->values();

        return [
            'gold' => $gold,
            'token_count' => $tokenCount,
            'token_unit_value' => $tokenUnitValue,
            'inventory_value' => (int) $items->sum(fn ($item) => $item->quantity * $item->unit_value),
            'items' => $items,
            'recent_drops' => $recentDrops,
            'transactions' => $transactionQuery->latest('id')->limit(50)->get(),
            'gold_transactions' => TreasuryTransaction::query()
                ->with('creator:id,discord_username,discord_display_name')
                ->latest('id')
                ->limit(50)
                ->get(),
        ];
    }
}
