<?php

namespace App\Http\Controllers;

use App\Models\PrimePlayerEarning;
use App\Models\TreasuryTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PayoutPreviewController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()->canCreatePayouts(), 403);
        $data = $request->validate([
            'period_from' => ['required', 'date'],
            'period_to' => ['required', 'date', 'after_or_equal:period_from'],
        ]);
        $from = CarbonImmutable::parse($data['period_from'])->startOfDay();
        $to = CarbonImmutable::parse($data['period_to'])->endOfDay();
        $earnings = PrimePlayerEarning::query()
            ->where('status', 'pending')
            ->whereNull('payout_id')
            ->whereHas('activity', fn ($query) => $query->whereBetween('occurred_at', [$from, $to]));
        $amount = (int) (clone $earnings)->sum('player_share');
        $balance = (int) (TreasuryTransaction::query()->latest('id')->value('balance_after') ?? 0);

        return response()->json([
            'amount' => $amount,
            'players' => (clone $earnings)->distinct()->count('player_id'),
            'activities' => (clone $earnings)->distinct()->count('activity_id'),
            'balance_before' => $balance,
            'balance_after' => $balance - $amount,
            'sufficient' => $balance >= $amount,
        ]);
    }
}
