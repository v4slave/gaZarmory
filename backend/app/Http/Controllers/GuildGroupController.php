<?php

namespace App\Http\Controllers;

use App\Models\GuildGroup;
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
        return GuildGroup::query()
            ->with(['players' => fn ($query) => $query
                ->where('is_active', true)
                ->with('user:id,role,roles')
                ->select(['id', 'user_id', 'group_id', 'nickname', 'class'])
                ->orderBy('nickname')])
            ->withCount(['players' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('name')
            ->get();
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
