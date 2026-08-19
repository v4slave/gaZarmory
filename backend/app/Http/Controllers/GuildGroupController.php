<?php

namespace App\Http\Controllers;

use App\Models\GuildGroup;
use App\Models\Activity;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class GuildGroupController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', GuildGroup::class);
        $periodStart = now()->subDays(30);
        $totalPrimes = Activity::query()->where('occurred_at', '>=', $periodStart)
            ->whereHas('definition', fn ($query) => $query->where('type', 'prime'))->count();
        $groups = GuildGroup::query()
            ->with(['players' => fn ($query) => $query
                ->where('is_active', true)
                ->with('user:id,discord_id,discord_username,discord_display_name,discord_avatar,role,roles')
                ->select(['id', 'user_id', 'group_id', 'nickname', 'class'])
                ->withCount([
                    'activities as primes_count' => fn ($activityQuery) => $activityQuery->where('occurred_at', '>=', $periodStart)->whereHas('definition', fn ($definitionQuery) => $definitionQuery->where('type', 'prime')),
                    'activities as mini_activities_count' => fn ($activityQuery) => $activityQuery->whereHas('definition', fn ($definitionQuery) => $definitionQuery->where('type', 'mini_activity')),
                ])
                ->orderBy('nickname')])
            ->withCount(['players' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('name')
            ->get();
        return $groups->each(function (GuildGroup $group) use ($totalPrimes): void {
            $group->setAttribute('average_gear_score', (int) round($group->players->avg('gear_score') ?? 0));
            $group->setAttribute('average_prime_attendance', $totalPrimes > 0
                ? round($group->players->avg(fn ($player) => $player->primes_count / $totalPrimes * 100) ?? 0, 1)
                : 0);
        });
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', GuildGroup::class);
        $group = GuildGroup::query()->create($request->validate(['name' => ['required', 'string', 'max:120', 'unique:groups,name']]));
        $this->audit->record('group.created', $group, null, $group->getAttributes());
        return response()->json($group, 201);
    }

    public function update(Request $request, GuildGroup $group): GuildGroup
    {
        $this->authorize('update', $group);
        $old = ['name' => $group->name];
        $group->update($request->validate(['name' => ['required', 'string', 'max:120', Rule::unique('groups', 'name')->ignore($group)]]));
        $this->audit->record('group.updated', $group, $old, ['name' => $group->name]);
        return $group->refresh();
    }

    public function destroy(GuildGroup $group): JsonResponse
    {
        $this->authorize('delete', $group);
        DB::transaction(function () use ($group): void {
            $old = ['name' => $group->name, 'players_count' => $group->players()->count()];
            $group->players()->update(['group_id' => null]);
            $group->delete();
            $this->audit->record('group.deleted', $group, $old, null);
        });
        return response()->json(null, 204);
    }
}
