<?php

namespace App\Http\Controllers;

use App\Enums\PlayerClass;
use App\Enums\UserRole;
use App\Http\Requests\StorePlayerRequest;
use App\Http\Requests\UpdatePlayerRequest;
use App\Models\Player;
use App\Models\Activity;
use App\Models\PrimePlayerEarning;
use App\Models\PlayerGearScoreHistory;
use App\Rules\ValidPlayerNickname;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
            ->addSelect([
                'paid_total' => DB::table('payout_players')
                    ->selectRaw('COALESCE(SUM(amount), 0)')
                    ->whereColumn('player_id', 'players.id')
                    ->where('status', 'paid'),
            ])
            ->with(['group:id,name', 'user:id,discord_id,discord_username,discord_display_name,discord_avatar'])
            ->withCount([
                'activities as primes_count' => fn ($activityQuery) => $activityQuery
                    ->countedInStatistics()
                    ->whereHas('definition', fn ($definitionQuery) => $definitionQuery->where('type', 'prime')),
            ]);
        $query->when($filters['search'] ?? null, function ($query, string $value): void {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
            $query->whereRaw("nickname ILIKE ? ESCAPE E'\\\\'", ['%'.$escaped.'%']);
        });
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

        $earnings = PrimePlayerEarning::query()->where('player_id', $player->id);

        $profile = $player
            ->load([
                'group',
                'user',
            ])
            ->toArray();

        $profile['statistics'] = [
            'period_days' => 30,
            'primes_count' => $visitedPrimes,
            'prime_attendance_percentage' => $totalPrimes > 0
                ? round($visitedPrimes / $totalPrimes * 100, 2)
                : 0,
            'paid_gold' => (int) (clone $earnings)->where('status', 'paid')->sum('player_share'),
            'pending_gold' => (int) (clone $earnings)->where('status', 'pending')->sum('player_share'),
        ];

        return response()->json($profile);
    }

    public function activities(Request $request, Player $player): JsonResponse
    {
        $this->authorize('view', $player);
        $perPage = $this->validatedPerPage($request);

        return response()->json(
            $player->activities()
                ->with('definition:id,name,type,icon_path')
                ->whereHas('definition', fn ($query) => $query->where('type', 'prime'))
                ->latest('occurred_at')
                ->paginate($perPage)
        );
    }

    public function earnings(Request $request, Player $player): JsonResponse
    {
        $this->authorize('view', $player);
        $perPage = $this->validatedPerPage($request);

        return response()->json(PrimePlayerEarning::query()
            ->where('player_id', $player->id)
            ->whereHas('activity.definition', fn ($query) => $query->where('type', 'prime'))
            ->with(['activity.definition:id,name,type,icon_path','payout:id,status,paid_at'])
            ->latest('id')
            ->paginate($perPage));
    }

    public function gearScoreHistory(Request $request, Player $player): JsonResponse
    {
        $this->authorize('view', $player);
        $perPage = $this->validatedPerPage($request);

        return response()->json(PlayerGearScoreHistory::query()
            ->where('player_id', $player->id)
            ->latest('recorded_at')
            ->latest('id')
            ->paginate($perPage));
    }

    private function validatedPerPage(Request $request): int
    {
        return (int) ($request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ])['per_page'] ?? 20);
    }

    public function update(UpdatePlayerRequest $request, Player $player): Player
    {
        $old = $player->only(['nickname', 'class', 'group_id', 'is_active']);
        $player->update($request->validated());
        if ($player->wasChanged('group_id')) DB::table('party_squad_players')->where('player_id', $player->id)->delete();
        $this->audit->record('player.updated', $player, $old, $player->only(['nickname', 'class', 'group_id', 'is_active']));
        return $player->refresh()->load(['group', 'user']);
    }

    public function updateProfile(Request $request, Player $player): Player
    {
        abort_unless($request->user()->hasRole(UserRole::Developer), 403);

        $assetFields = ['has_ship','has_tank','has_fuchsias','has_clouds','has_machaon','has_tare','has_deer','has_invulnerable_pet','has_shield_swap','has_flippers'];
        $rules = [
            'nickname' => ['required', 'string', new ValidPlayerNickname(), Rule::unique('players', 'nickname')->ignore($player)],
            'class' => ['required', Rule::enum(PlayerClass::class)],
            'gear_score' => ['required', 'integer', 'min:0', 'max:100000'],
        ];
        foreach ($assetFields as $field) $rules[$field] = ['required', 'boolean'];
        $data = $request->validate($rules);

        return DB::transaction(function () use ($player, $data): Player {
            $trackedFields = array_keys($data);
            $old = $player->only($trackedFields);

            if ((int) $data['gear_score'] !== (int) $player->gear_score) {
                $data['previous_gear_score'] = $player->gear_score;
                $data['gear_score_updated_at'] = now();
                PlayerGearScoreHistory::query()->create([
                    'player_id' => $player->id,
                    'gear_score' => $data['gear_score'],
                    'recorded_at' => now(),
                ]);
            }

            $player->update($data);
            $this->audit->record('player.profile_updated_by_developer', $player, $old, $player->only($trackedFields));

            return $player->refresh()->load(['group', 'user']);
        });
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
        if ($player->wasChanged('group_id')) DB::table('party_squad_players')->where('player_id', $player->id)->delete();
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

    public function destroyPermanently(Player $player): JsonResponse
    {
        $this->authorize('delete', $player);

        DB::transaction(function () use ($player): void {
            $lockedPlayer = Player::query()->lockForUpdate()->findOrFail($player->id);

            if ($lockedPlayer->user_id !== null) {
                throw ValidationException::withMessages([
                    'player' => [__('domain.profile.unlink_before_delete')],
                ]);
            }

            if ($this->hasHistory($lockedPlayer->id)) {
                throw ValidationException::withMessages([
                    'player' => [__('domain.profile.has_history')],
                ]);
            }

            $snapshot = $lockedPlayer->getAttributes();
            $this->audit->record('player.deleted', $lockedPlayer, $snapshot, null);
            $lockedPlayer->delete();
        });

        return response()->json(null, 204);
    }

    private function hasHistory(int $playerId): bool
    {
        return DB::table('activity_players')->where('player_id', $playerId)->exists()
            || DB::table('prime_player_earnings')->where('player_id', $playerId)->exists()
            || DB::table('treasury_item_transactions')->where('recipient_player_id', $playerId)->exists()
            || DB::table('auctions')->where('winner_player_id', $playerId)->exists()
            || DB::table('auction_bids')->where('player_id', $playerId)->exists()
            || DB::table('payout_players')->where('player_id', $playerId)->exists();
    }

    public function activate(Player $player): JsonResponse
    {
        $this->authorize('update', $player);
        $old = ['is_active' => $player->is_active];
        $player->update(['is_active' => true]);
        $this->audit->record('player.activated', $player, $old, ['is_active' => true]);

        return response()->json($player->refresh()->load(['group', 'user']));
    }
}
