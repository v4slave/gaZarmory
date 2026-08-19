<?php

namespace App\Http\Controllers;

use App\Models\PrimePlayerEarning;
use App\Models\TreasuryTransaction;
use App\Models\Activity;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PayoutPreviewController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()->canCreatePayouts(), 403);
        $data = $request->validate([
            'period_from' => ['nullable', 'required_without:activity_ids', 'date'],
            'period_to' => ['nullable', 'required_without:activity_ids', 'date', 'after_or_equal:period_from'],
            'activity_ids' => ['nullable','array','min:1'],
            'activity_ids.*' => ['integer','distinct','exists:activities,id'],
        ]);
        $from = isset($data['period_from']) ? CarbonImmutable::parse($data['period_from'])->startOfDay() : null;
        $to = isset($data['period_to']) ? CarbonImmutable::parse($data['period_to'])->endOfDay() : null;
        $earnings = PrimePlayerEarning::query()
            ->where('status', 'pending')
            ->whereNull('payout_id')
            ->whereHas('activity.definition', fn ($query) => $query->where('type', 'prime'))
            ->whereHas('activity', fn ($query) => $query
                ->when($from,fn($q)=>$q->whereBetween('occurred_at',[$from,$to]))
                ->when($data['activity_ids']??null,fn($q,$ids)=>$q->whereIn('id',$ids)));
        $amount = (int) (clone $earnings)->sum('player_share');
        $balance = (int) (TreasuryTransaction::query()->latest('id')->value('balance_after') ?? 0);
        $tokenUnitValue = (int) (DB::table('treasury_token_settings')->where('id', 1)->value('token_unit_value') ?? 0);

        return response()->json([
            'amount' => $amount,
            'token_unit_value' => $tokenUnitValue,
            'players' => (clone $earnings)->distinct()->count('player_id'),
            'activities' => (clone $earnings)->distinct()->count('activity_id'),
            'balance_before' => $balance,
            'balance_after' => $balance - $amount,
            'sufficient' => $balance >= $amount,
            'rows' => (clone $earnings)->selectRaw('player_id, MAX(nickname_snapshot) AS nickname, SUM(player_share) AS amount, COUNT(DISTINCT activity_id) AS activities_count')->groupBy('player_id')->orderBy('nickname')->get(),
            'activity_options' => Activity::query()->whereHas('definition',fn($q)=>$q->where('type','prime'))->whereHas('earnings',fn($q)=>$q->where('status','pending')->whereNull('payout_id'))->with('definition:id,name,type')->latest('occurred_at')->limit(200)->get(['id','activity_definition_id','occurred_at']),
        ]);
    }
}
