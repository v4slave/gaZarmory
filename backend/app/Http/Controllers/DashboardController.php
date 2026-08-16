<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Auction;
use App\Models\Player;
use App\Models\PrimePlayerEarning;
use App\Models\TreasuryItem;
use Illuminate\Support\Facades\DB;

final class DashboardController extends Controller
{
    public function __invoke(): array
    {
        $periodStart = now()->subDays(30);
        $totalPrimes = Activity::query()
            ->where('occurred_at', '>=', $periodStart)
            ->whereHas('definition', fn ($query) => $query->where('type', 'prime'))
            ->count();

        $attendanceTop = Player::query()
            ->where('is_active', true)
            ->withCount([
                'activities as primes_count' => fn ($query) => $query
                    ->where('occurred_at', '>=', $periodStart)
                    ->whereHas('definition', fn ($definition) => $definition->where('type', 'prime')),
                'activities as mini_activities_count' => fn ($query) => $query
                    ->where('occurred_at', '>=', $periodStart)
                    ->whereNotNull('completed_at')
                    ->whereHas('definition', fn ($definition) => $definition->where('type', 'mini_activity')),
            ])
            ->get(['id', 'nickname', 'class'])
            ->filter(fn (Player $player) => $player->primes_count + $player->mini_activities_count > 0)
            ->map(function (Player $player) use ($totalPrimes): array {
                return [
                    'id' => $player->id,
                    'nickname' => $player->nickname,
                    'class' => $player->class->value,
                    'primes_count' => $player->primes_count,
                    'mini_activities_count' => $player->mini_activities_count,
                    'attendance_percentage' => $totalPrimes > 0
                        ? round($player->primes_count / $totalPrimes * 100, 2)
                        : 0,
                ];
            })
            ->sort(fn (array $left, array $right) =>
                ($right['attendance_percentage'] <=> $left['attendance_percentage'])
                ?: ($right['primes_count'] <=> $left['primes_count'])
                ?: ($right['mini_activities_count'] <=> $left['mini_activities_count'])
                ?: strcasecmp($left['nickname'], $right['nickname']))
            ->take(5)
            ->values();

        $items = TreasuryItem::query()->where('quantity', '>', 0)->get();

        return [
            'gold' => (int) (DB::table('treasury_transactions')->latest('id')->value('balance_after') ?? 0),
            'inventory_value' => (int) $items->sum(fn ($item) => $item->quantity * $item->unit_value),
            'pending_payout' => (int) PrimePlayerEarning::query()->where('status', 'pending')->sum('player_share'),
            'active_auctions' => Auction::query()->where('status', 'active')->count(),
            'attendance_period_days' => 30,
            'attendance_top' => $attendanceTop,
            'recent_activities' => Activity::query()
                ->with('definition:id,name,type,icon_path')
                ->withCount('players')
                ->latest('occurred_at')
                ->limit(5)
                ->get(),
        ];
    }
}
