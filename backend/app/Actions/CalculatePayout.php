<?php

namespace App\Actions;

use App\Models\Activity;
use App\Models\Payout;
use App\Models\PayoutPlayer;
use App\Models\PrimePlayerEarning;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CalculatePayout
{
    public function __construct(private readonly AuditService $audit) {}

    public function execute(Payout $payout): Payout
    {
        return DB::transaction(function () use ($payout): Payout {
            $locked = Payout::query()->lockForUpdate()->findOrFail($payout->id);
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['payout' => 'Рассчитать можно только черновик.']);
            }

            $earnings = PrimePlayerEarning::query()
                ->where('status', 'pending')
                ->whereNull('payout_id')
                ->whereHas('activity.definition', fn ($query) => $query->where('type', 'prime'))
                ->whereHas('activity', fn ($query) => $query
                    ->whereDate('occurred_at', '>=', $locked->period_from)
                    ->whereDate('occurred_at', '<=', $locked->period_to))
                ->lockForUpdate()
                ->get();

            if ($earnings->isEmpty()) {
                throw ValidationException::withMessages(['payout' => 'За выбранный период нет свободных начислений.']);
            }

            $activityIds = $earnings->pluck('activity_id')->unique()->values();

            DB::table('payout_activities')
                ->whereIn('activity_id', $activityIds)
                ->whereIn('payout_id', Payout::query()->where('status', 'cancelled')->select('id'))
                ->delete();

            $locked->activities()->syncWithoutDetaching($activityIds);

            $totalPrimes = Activity::query()
                ->whereHas('definition', fn ($query) => $query->where('type', 'prime'))
                ->whereDate('occurred_at', '>=', $locked->period_from)
                ->whereDate('occurred_at', '<=', $locked->period_to)
                ->count();

            foreach ($earnings->groupBy('player_id') as $playerId => $rows) {
                $earning = $rows->first();
                $visited = Activity::query()
                    ->whereHas('definition', fn ($query) => $query->where('type', 'prime'))
                    ->whereHas('players', fn ($query) => $query->where('players.id', $playerId))
                    ->whereDate('occurred_at', '>=', $locked->period_from)
                    ->whereDate('occurred_at', '<=', $locked->period_to)
                    ->count();
                PayoutPlayer::query()->create([
                    'payout_id' => $locked->id,
                    'player_id' => $playerId,
                    'nickname_snapshot' => $earning->nickname_snapshot,
                    'prime_attendance_percentage_snapshot' => $totalPrimes ? round($visited / $totalPrimes * 100, 2) : 0,
                    'primes_count' => $visited,
                    'mini_activities_count' => 0,
                    'amount' => $rows->sum('player_share'),
                    'status' => 'pending',
                ]);
            }

            PrimePlayerEarning::query()->whereKey($earnings->modelKeys())->update(['payout_id' => $locked->id]);
            $total = (int) $earnings->sum('player_share');
            $locked->update(['status' => 'calculated', 'total_amount' => $total, 'calculated_at' => now()]);
            $this->audit->record('payout.calculated', $locked, ['status' => 'draft'], ['status' => 'calculated', 'total_amount' => $total]);

            return $locked->refresh()->load(['players', 'activities.definition']);
        });
    }
}
