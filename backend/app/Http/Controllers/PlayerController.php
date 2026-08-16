<?php

namespace App\Http\Controllers;

use App\Enums\PlayerClass;
use App\Enums\UserRole;
use App\Http\Requests\StorePlayerRequest;
use App\Http\Requests\UpdatePlayerRequest;
use App\Models\Player;
use App\Models\Activity;
use App\Models\PrimePlayerEarning;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class PlayerController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Player::class);
        foreach (['solo', 'active'] as $booleanFilter) {
            if ($request->has($booleanFilter)) {
                $request->merge([$booleanFilter => $request->boolean($booleanFilter)]);
            }
        }
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'class' => ['nullable', Rule::enum(PlayerClass::class)],
            'group_id' => ['nullable', 'integer'], 'solo' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'], 'sort' => ['nullable', Rule::in(['nickname', 'class', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])], 'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $query = Player::query()
            ->with(['group:id,name', 'user:id,discord_id,discord_username,discord_display_name'])
            ->withCount([
                'activities as primes_count' => fn ($activityQuery) => $activityQuery
                    ->whereHas('definition', fn ($definitionQuery) => $definitionQuery->where('type', 'prime')),
                'activities as mini_activities_count' => fn ($activityQuery) => $activityQuery
                    ->whereNotNull('completed_at')
                    ->whereHas('definition', fn ($definitionQuery) => $definitionQuery->where('type', 'mini_activity')),
            ]);
        $query->when($filters['search'] ?? null, fn ($q, $value) => $q->where('nickname', 'ilike', '%'.$value.'%'));
        $query->when($filters['class'] ?? null, fn ($q, $value) => $q->where('class', $value));
        $query->when(array_key_exists('active', $filters), fn ($q) => $q->where('is_active', $filters['active']));
        $query->when($filters['group_id'] ?? null, fn ($q, $value) => $q->where('group_id', $value));
        $query->when($filters['solo'] ?? false, fn ($q) => $q->whereNull('group_id'));

        return $query->orderBy($filters['sort'] ?? 'nickname', $filters['direction'] ?? 'asc')->paginate($filters['per_page'] ?? 25);
    }

    public function store(StorePlayerRequest $request): JsonResponse
    {
        $player = Player::query()->create($request->validated());
        $this->audit->record('player.created', $player, null, $player->getAttributes());
        return response()->json($player->load('group'), 201);
    }

    public function show(Player $player): JsonResponse
    {
        $this->authorize('view', $player);

        $periodStart = now()->subDays(30);
        $periodEnd = now();

        $activitiesInPeriod = Activity::query()
            ->whereBetween('occurred_at', [$periodStart, $periodEnd]);

        $totalPrimes = (clone $activitiesInPeriod)
            ->whereHas('definition', fn ($query) => $query->where('type', 'prime'))
            ->count();

        $visitedPrimes = (clone $activitiesInPeriod)
            ->whereHas('definition', fn ($query) => $query->where('type', 'prime'))
            ->whereHas('players', fn ($query) => $query->where('players.id', $player->id))
            ->count();

        $miniActivities = (clone $activitiesInPeriod)
            ->whereNotNull('completed_at')
            ->whereHas('definition', fn ($query) => $query->where('type', 'mini_activity'))
            ->whereHas('players', fn ($query) => $query->where('players.id', $player->id))
            ->count();

        $earnings = PrimePlayerEarning::query()->where('player_id', $player->id);

        $profile = $player
            ->load([
                'group',
                'user',
                'activities' => fn ($query) => $query
                    ->with('definition')
                    ->latest('occurred_at'),
            ])
            ->toArray();

        $profile['statistics'] = [
            'period_days' => 30,
            'primes_count' => $visitedPrimes,
            'mini_activities_count' => $miniActivities,
            'activities_count' => count($profile['activities']),
            'prime_attendance_percentage' => $totalPrimes > 0
                ? round($visitedPrimes / $totalPrimes * 100, 2)
                : 0,
            'paid_gold' => (int) (clone $earnings)->where('status', 'paid')->sum('player_share'),
            'pending_gold' => (int) (clone $earnings)->where('status', 'pending')->sum('player_share'),
        ];
        $profile['earnings_history'] = PrimePlayerEarning::query()
            ->where('player_id', $player->id)
            ->with(['activity.definition:id,name,type','payout:id,status,paid_at'])
            ->latest('id')
            ->get();

        return response()->json($profile);
    }

    public function update(UpdatePlayerRequest $request, Player $player): Player
    {
        $old = $player->only(['nickname', 'class', 'group_id', 'is_active']);
        $player->update($request->validated());
        $this->audit->record('player.updated', $player, $old, $player->only(['nickname', 'class', 'group_id', 'is_active']));
        return $player->refresh()->load(['group', 'user']);
    }

    public function move(Request $request, Player $player): Player
    {
        $this->authorize('move', $player);
        $data = $request->validate(['group_id' => ['nullable', 'integer', 'exists:groups,id']]);
        if (!$request->user()->canManageGuild() && $request->user()->hasRole(UserRole::PartyLeader)) {
            $ownGroupId = $request->user()->player?->group_id;
            $targetGroupId = $data['group_id'] ?? null;
            abort_unless($ownGroupId !== null && ($targetGroupId === null || $targetGroupId === $ownGroupId), 403);
        }
        $old = ['group_id' => $player->group_id];
        $player->update($data);
        $this->audit->record('player.group_changed', $player, $old, $data);
        return $player->refresh()->load('group');
    }

    public function destroy(Player $player): JsonResponse
    {
        $this->authorize('delete', $player);
        $old = ['is_active' => $player->is_active];
        $player->update(['is_active' => false]);
        $this->audit->record('player.deactivated', $player, $old, ['is_active' => false]);
        return response()->json(null, 204);
    }
}
