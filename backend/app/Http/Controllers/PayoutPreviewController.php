<?php

namespace App\Http\Controllers;

use App\Models\PrimePlayerEarning;
use App\Models\TreasuryTransaction;
use App\Models\Activity;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
            'distribution_amount' => ['nullable','integer','min:1','max:1000000000'],
            'distribution_currency' => ['nullable','required_with:distribution_amount','in:gold,tokens'],
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
        $sourceAmount = (int) (clone $earnings)->sum('player_share');
        $balance = (int) (TreasuryTransaction::query()->latest('id')->value('balance_after') ?? 0);
        $tokenUnitValue = (int) (DB::table('treasury_token_settings')->where('id', 1)->value('token_unit_value') ?? 0);
        if (($data['distribution_currency'] ?? null) === 'tokens' && $tokenUnitValue <= 0) {
            throw ValidationException::withMessages(['distribution_amount' => 'Сначала задайте стоимость жетона в настройках экономики.']);
        }
        $amount = isset($data['distribution_amount'])
            ? (int) $data['distribution_amount'] * (($data['distribution_currency'] ?? 'gold') === 'tokens' ? $tokenUnitValue : 1)
            : $sourceAmount;

        $rows = (clone $earnings)->selectRaw('player_id, MAX(nickname_snapshot) AS nickname, SUM(player_share) AS amount, COUNT(DISTINCT activity_id) AS activities_count')->groupBy('player_id')->orderBy('nickname')->get();
        if (isset($data['distribution_amount']) && $sourceAmount > 0) {
            $used = 0;
            foreach ($rows as $row) {
                $numerator = $amount * (int) $row->amount;
                $row->setAttribute('_remainder', $numerator % $sourceAmount);
                $row->amount = intdiv($numerator, $sourceAmount);
                $used += (int) $row->amount;
            }
            foreach ($rows->sortByDesc('_remainder')->take($amount - $used) as $row) $row->amount++;
            $rows->each(fn ($row) => $row->offsetUnset('_remainder'));
        }

        return response()->json([
            'amount' => $amount,
            'source_amount' => $sourceAmount,
            'token_unit_value' => $tokenUnitValue,
            'players' => (clone $earnings)->distinct()->count('player_id'),
            'activities' => (clone $earnings)->distinct()->count('activity_id'),
            'balance_before' => $balance,
            'balance_after' => $balance - $amount,
            'sufficient' => $balance >= $amount,
            'rows' => $rows,
            'activity_options' => Activity::query()->whereHas('definition',fn($q)=>$q->where('type','prime'))->whereHas('earnings',fn($q)=>$q->where('status','pending')->whereNull('payout_id'))->with('definition:id,name,type,icon_path')->latest('occurred_at')->limit(200)->get(['id','activity_definition_id','occurred_at']),
        ]);
    }
}
