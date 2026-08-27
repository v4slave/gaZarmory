<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

final class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdministrator($request);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $users = User::query()
            ->with(['player.group:id,name'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
                $query->where(fn ($nested) => $nested
                    ->where('discord_username', 'ilike', '%'.$escaped.'%')
                    ->orWhere('discord_display_name', 'ilike', '%'.$escaped.'%')
                    ->orWhereHas('player', fn ($player) => $player->where('nickname', 'ilike', '%'.$escaped.'%')));
            })
            ->orderByRaw("CASE WHEN roles @> '[\"guild_leader\"]'::jsonb THEN 1 WHEN roles @> '[\"micro_guild_leader\"]'::jsonb THEN 2 WHEN roles @> '[\"developer\"]'::jsonb THEN 3 WHEN roles @> '[\"party_leader\"]'::jsonb THEN 4 ELSE 5 END")
            ->orderBy('discord_username')
            ->paginate(25);

        return response()->json($users);
    }

    public function updateRole(
        Request $request,
        User $managedUser,
        AuditService $audit,
    ): JsonResponse {
        $this->authorizeAdministrator($request);
        $data = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'distinct', Rule::enum(UserRole::class)],
            'updated_at' => ['nullable', 'date'],
        ]);
        $newRoles = array_values($data['roles']);
        $managedRoles = $managedUser->roles ?: [$managedUser->role->value];
        $hadDeveloper = in_array(UserRole::Developer->value, $managedRoles, true);
        $willHaveDeveloper = in_array(UserRole::Developer->value, $newRoles, true);
        if ($hadDeveloper !== $willHaveDeveloper) {
            if (! $request->user()->hasRole(UserRole::Developer)) {
                throw ValidationException::withMessages(['roles' => __('domain.admin.developer_assignment_restricted')]);
            }
            if ($request->user()->is($managedUser)) {
                throw ValidationException::withMessages(['roles' => __('domain.admin.cannot_change_own_developer_role')]);
            }
        }
        $wasLeader = in_array(UserRole::GuildLeader->value, $managedRoles, true);
        $willBeLeader = in_array(UserRole::GuildLeader->value, $newRoles, true);
        $isTransfer = ! $wasLeader && $willBeLeader;
        if ($isTransfer && ! $request->user()->hasRole(UserRole::GuildLeader)) {
            throw ValidationException::withMessages(['roles' => __('domain.admin.guild_leader_transfer_restricted')]);
        }
        if ($wasLeader && ! $willBeLeader) {
            throw ValidationException::withMessages(['roles' => __('domain.admin.guild_leader_removed_by_transfer')]);
        }

        $updated = DB::transaction(function () use ($request, $managedUser, $newRoles, $data, $audit, $isTransfer): User {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($managedUser->id);
            if (isset($data['updated_at']) && ! $lockedUser->updated_at->equalTo($data['updated_at'])) {
                throw ValidationException::withMessages(['updated_at' => __('domain.admin.user_stale')]);
            }
            $oldRoles = $lockedUser->roles ?: [$lockedUser->role->value];

            sort($oldRoles); $sortedNewRoles = $newRoles; sort($sortedNewRoles);
            if ($oldRoles === $sortedNewRoles) {
                return $lockedUser;
            }

            if ($isTransfer) {
                $leaders = User::query()->whereJsonContains('roles', UserRole::GuildLeader->value)->lockForUpdate()->get();
                if (! $leaders->contains('id', $request->user()->id)) {
                    throw ValidationException::withMessages(['roles' => __('domain.admin.guild_leader_role_lost')]);
                }
                foreach ($leaders as $leader) {
                    $leaderOldRoles = $leader->roles ?: [$leader->role->value];
                    $leaderRoles = array_values(array_filter(
                        $leaderOldRoles,
                        fn (string $role): bool => $role !== UserRole::GuildLeader->value,
                    ));
                    if ($leaderRoles === []) $leaderRoles = [UserRole::Member->value];
                    $leader->forceFill(['roles' => $leaderRoles, 'role' => User::primaryRoleFor($leaderRoles)])->save();
                    $audit->record('guild_leader.transferred_from', $leader, ['roles' => $leaderOldRoles], ['roles' => $leaderRoles, 'new_guild_leader_id' => $lockedUser->id]);
                }
            }

            $lockedUser->forceFill([
                'roles' => $newRoles,
                'role' => User::primaryRoleFor($newRoles),
            ])->save();
            $audit->record(
                $isTransfer ? 'guild_leader.transferred_to' : 'user.roles_changed',
                $lockedUser,
                ['roles' => $oldRoles],
                ['roles' => $newRoles],
            );

            return $lockedUser;
        });

        return response()->json($updated->refresh()->load(['player.group:id,name']));
    }

    public function destroy(Request $request, User $managedUser, AuditService $audit): JsonResponse
    {
        $this->authorizeAdministrator($request);
        if ($request->user()->is($managedUser)) {
            throw ValidationException::withMessages(['user' => __('domain.admin.cannot_delete_self')]);
        }
        $managedRoles = $managedUser->roles ?: [$managedUser->role->value];
        $actorCanManageElevated = $request->user()->hasRole(UserRole::GuildLeader)
            || $request->user()->hasRole(UserRole::Developer);
        if (! $actorCanManageElevated && array_intersect($managedRoles, [UserRole::GuildLeader->value, UserRole::Developer->value])) {
            throw ValidationException::withMessages(['user' => __('domain.admin.micro_leader_delete_restricted')]);
        }

        DB::transaction(function () use ($managedUser, $audit): void {
            $lockedUser = User::query()->lockForUpdate()->with('player')->findOrFail($managedUser->id);
            $roles = $lockedUser->roles ?: [$lockedUser->role->value];
            if (in_array(UserRole::GuildLeader->value, $roles, true)) {
                $leadersCount = User::query()
                    ->whereJsonContains('roles', UserRole::GuildLeader->value)
                    ->lockForUpdate()->get(['id'])->count();
                if ($leadersCount <= 1) {
                    throw ValidationException::withMessages(['user' => __('domain.admin.last_guild_leader')]);
                }
            }

            foreach (['activities', 'activity_loot', 'treasury_item_transactions', 'treasury_transactions', 'auctions', 'payouts', 'loot_imports'] as $table) {
                if (DB::table($table)->where('created_by', $lockedUser->id)->exists()) {
                    throw ValidationException::withMessages([
                        'user' => __('domain.admin.user_has_history'),
                    ]);
                }
            }

            $snapshot = $lockedUser->only(['id', 'discord_id', 'discord_username', 'discord_display_name']);
            $snapshot['player_id'] = $lockedUser->player?->id;
            $audit->record('user.deleted', $lockedUser, $snapshot, null);
            $lockedUser->delete();
        });

        return response()->json(null, 204);
    }

    private function authorizeAdministrator(Request $request): void
    {
        abort_unless($request->user()?->canAdministrate(), 403);
    }
}
