<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\AuditService;
use App\Services\UserRoleManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class AdminUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdministrator($request);

        $users = User::query()
            ->with(['player.group:id,name'])
            ->orderByRaw("CASE WHEN roles @> '[\"guild_leader\"]'::jsonb THEN 1 WHEN roles @> '[\"developer\"]'::jsonb THEN 2 WHEN roles @> '[\"party_leader\"]'::jsonb THEN 3 ELSE 4 END")
            ->orderBy('discord_username')
            ->get();

        return response()->json($users);
    }

    public function updateRole(
        Request $request,
        User $managedUser,
        AuditService $audit,
        UserRoleManager $roleManager,
    ): JsonResponse {
        $this->authorizeAdministrator($request);
        $data = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'distinct', Rule::enum(UserRole::class)],
        ]);
        $newRoles = array_values($data['roles']);

        $updated = DB::transaction(function () use ($managedUser, $newRoles, $audit, $roleManager): User {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($managedUser->id);
            $oldRoles = $lockedUser->roles ?: [$lockedUser->role->value];

            sort($oldRoles); $sortedNewRoles = $newRoles; sort($sortedNewRoles);
            if ($oldRoles === $sortedNewRoles) {
                return $lockedUser;
            }

            $leadersCount = User::query()
                ->whereJsonContains('roles', UserRole::GuildLeader->value)
                ->lockForUpdate()
                ->get(['id'])
                ->count();
            $roleManager->ensureLeaderRemains(
                in_array(UserRole::GuildLeader->value, $oldRoles, true),
                in_array(UserRole::GuildLeader->value, $newRoles, true),
                $leadersCount,
            );

            $lockedUser->forceFill([
                'roles' => $newRoles,
                'role' => User::primaryRoleFor($newRoles),
            ])->save();
            $audit->record(
                'user.roles_changed',
                $lockedUser,
                ['roles' => $oldRoles],
                ['roles' => $newRoles],
            );

            return $lockedUser;
        });

        return response()->json($updated->refresh()->load(['player.group:id,name']));
    }

    private function authorizeAdministrator(Request $request): void
    {
        abort_unless($request->user()?->canAdministrate(), 403);
    }
}
