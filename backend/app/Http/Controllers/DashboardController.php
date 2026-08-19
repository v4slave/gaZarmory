<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityDefinition;
use App\Models\Auction;
use App\Models\Player;
use App\Models\PrimePlayerEarning;
use App\Models\TreasuryItem;
use Carbon\CarbonImmutable;
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
            ->with('user:id,discord_id,discord_username,discord_display_name,discord_avatar')
            ->withCount([
                'activities as primes_count' => fn ($query) => $query
                    ->where('occurred_at', '>=', $periodStart)
                    ->whereHas('definition', fn ($definition) => $definition->where('type', 'prime')),
                'activities as mini_activities_count' => fn ($query) => $query
                    ->where('occurred_at', '>=', $periodStart)
                    ->whereNotNull('completed_at')
                    ->whereHas('definition', fn ($definition) => $definition->where('type', 'mini_activity')),
            ])
            ->get(['id', 'user_id', 'nickname', 'class'])
            ->filter(fn (Player $player) => $player->primes_count + $player->mini_activities_count > 0)
            ->map(function (Player $player) use ($totalPrimes): array {
                return [
                    'id' => $player->id,
                    'nickname' => $player->nickname,
                    'class' => $player->class->value,
                    'user' => $player->user,
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
        $currentInventoryValue = (int) $items->sum(fn ($item) => $item->quantity * $item->unit_value);
        $activePlayers = Player::query()->where('is_active', true);
        $classDistribution = (clone $activePlayers)->select('class', DB::raw('COUNT(*) as total'))->groupBy('class')->pluck('total', 'class');

        return [
            'gold' => (int) (DB::table('treasury_transactions')->latest('id')->value('balance_after') ?? 0),
            'inventory_value' => $currentInventoryValue,
            'pending_payout' => (int) PrimePlayerEarning::query()->where('status', 'pending')->sum('player_share'),
            'active_auctions' => Auction::query()->where('status', 'active')->count(),
            'average_gear_score' => (int) round((clone $activePlayers)->avg('gear_score') ?? 0),
            'class_distribution' => $classDistribution,
            'treasury_dynamics' => $this->treasuryDynamics($currentInventoryValue),
            'upcoming_events' => $this->upcomingEvents(),
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

    private function treasuryDynamics(int $currentInventoryValue): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $periodStart = $today->subDays(13);
        $periodEnd = $today->addDay();

        $gold = (int) (DB::table('treasury_transactions')
            ->where('created_at', '<', $periodStart)
            ->latest('id')
            ->value('balance_after') ?? 0);
        $goldTransactions = DB::table('treasury_transactions')
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->orderBy('created_at')->orderBy('id')
            ->get(['created_at', 'balance_after']);

        $inventory = (int) DB::table('treasury_item_transactions as transactions')
            ->join('treasury_items as items', 'items.id', '=', 'transactions.treasury_item_id')
            ->where('transactions.created_at', '<', $periodStart)
            ->sum(DB::raw('transactions.quantity_delta * items.unit_value'));
        $inventoryTransactions = DB::table('treasury_item_transactions as transactions')
            ->join('treasury_items as items', 'items.id', '=', 'transactions.treasury_item_id')
            ->whereBetween('transactions.created_at', [$periodStart, $periodEnd])
            ->orderBy('transactions.created_at')->orderBy('transactions.id')
            ->get(['transactions.created_at', 'transactions.quantity_delta', 'items.unit_value']);

        $goldIndex = 0;
        $inventoryIndex = 0;

        return collect(range(0, 13))->map(function (int $offset) use (
            $periodStart, $goldTransactions, $inventoryTransactions, &$goldIndex, &$inventoryIndex, &$gold, &$inventory, $currentInventoryValue
        ): array {
            $date = $periodStart->addDays($offset);
            $endOfDay = $date->addDay();

            while (isset($goldTransactions[$goldIndex]) && CarbonImmutable::parse($goldTransactions[$goldIndex]->created_at)->lt($endOfDay)) {
                $gold = (int) $goldTransactions[$goldIndex]->balance_after;
                $goldIndex++;
            }
            while (isset($inventoryTransactions[$inventoryIndex]) && CarbonImmutable::parse($inventoryTransactions[$inventoryIndex]->created_at)->lt($endOfDay)) {
                $transaction = $inventoryTransactions[$inventoryIndex];
                $inventory += (int) $transaction->quantity_delta * (int) $transaction->unit_value;
                $inventoryIndex++;
            }

            return [
                'date' => $date->toDateString(),
                'gold' => $gold,
                'inventory_value' => $offset === 13 ? $currentInventoryValue : max(0, $inventory),
            ];
        })->all();
    }

    private function upcomingEvents(): array
    {
        $schedule = [
            1 => [['10:00','Кошка'],['19:30','Кракен'],['20:30','Калидис'],['21:30','Анталлон'],['22:00','Кошка']],
            2 => [['10:00','Кошка'],['19:30','Ксанатос'],['20:30','Левиафан'],['22:00','Кошка']],
            3 => [['10:00','Кошка'],['22:00','Кошка']],
            4 => [['10:00','Кошка'],['19:30','Кракен'],['20:30','Левиафан'],['22:00','Кошка']],
            5 => [['10:00','Кошка'],['19:30','Ксанатос'],['20:30','Калидис'],['21:30','Анталлон'],['22:00','Кошка']],
            6 => [['10:00','Кошка'],['19:30','Кракен'],['20:30','Калидис'],['22:00','Кошка']],
            7 => [['10:00','Кошка'],['19:30','Ксанатос'],['19:50','Анталлон'],['20:30','Левиафан'],['22:00','Кошка']],
        ];
        $now = CarbonImmutable::now('Europe/Moscow');
        $definitions = ActivityDefinition::query()->whereNotNull('icon_path')->get()->keyBy(fn ($item) => mb_strtolower($item->name));
        $events = collect();

        foreach (range(0, 7) as $offset) {
            $day = $now->startOfDay()->addDays($offset);
            foreach ($schedule[$day->dayOfWeekIso] as [$time, $name]) {
                $startsAt = $day->setTimeFromTimeString($time);
                if ($startsAt->isBefore($now)) continue;
                $definition = $definitions->get(mb_strtolower($name));
                $events->push([
                    'name' => $name,
                    'starts_at' => $startsAt->toIso8601String(),
                    'icon_url' => $definition?->icon_url,
                ]);
            }
        }

        return $events->sortBy('starts_at')->take(6)->values()->all();
    }
}
