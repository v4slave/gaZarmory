<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\AuditService;
use App\Services\UserRoleManager;
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
        UserRoleManager $roleManager,
    ): JsonResponse {
        $this->authorizeAdministrator($request);
        $data = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'distinct', Rule::enum(UserRole::class)],
            'updated_at' => ['nullable', 'date'],
        ]);
        $newRoles = array_values($data['roles']);
        $actorCanAssignElevatedRoles = $request->user()->hasRole(UserRole::GuildLeader)
            || $request->user()->hasRole(UserRole::Developer);
        $managedRoles = $managedUser->roles ?: [$managedUser->role->value];
        $elevatedRoles = [UserRole::GuildLeader->value, UserRole::Developer->value];
        if (! $actorCanAssignElevatedRoles && (array_intersect($managedRoles, $elevatedRoles) || array_intersect($newRoles, $elevatedRoles))) {
            throw ValidationException::withMessages(['roles' => 'Микро-ГЛ не может назначать или изменять роли ГЛ и Разработчик.']);
        }

        $updated = DB::transaction(function () use ($managedUser, $newRoles, $data, $audit, $roleManager): User {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($managedUser->id);
            if (isset($data['updated_at']) && ! $lockedUser->updated_at->equalTo($data['updated_at'])) {
                throw ValidationException::withMessages(['updated_at' => 'Данные пользователя уже изменены другим администратором. Обновите страницу.']);
            }
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

    public function destroy(Request $request, User $managedUser, AuditService $audit): JsonResponse
    {
        $this->authorizeAdministrator($request);
        if ($request->user()->is($managedUser)) {
            throw ValidationException::withMessages(['user' => 'Нельзя удалить собственный аккаунт.']);
        }
        $managedRoles = $managedUser->roles ?: [$managedUser->role->value];
        $actorCanManageElevated = $request->user()->hasRole(UserRole::GuildLeader)
            || $request->user()->hasRole(UserRole::Developer);
        if (! $actorCanManageElevated && array_intersect($managedRoles, [UserRole::GuildLeader->value, UserRole::Developer->value])) {
            throw ValidationException::withMessages(['user' => 'Микро-ГЛ не может удалить ГЛ или Разработчика.']);
        }

        DB::transaction(function () use ($managedUser, $audit): void {
            $lockedUser = User::query()->lockForUpdate()->with('player')->findOrFail($managedUser->id);
            $roles = $lockedUser->roles ?: [$lockedUser->role->value];
            if (in_array(UserRole::GuildLeader->value, $roles, true)) {
                $leadersCount = User::query()
                    ->whereJsonContains('roles', UserRole::GuildLeader->value)
                    ->lockForUpdate()->get(['id'])->count();
                if ($leadersCount <= 1) {
                    throw ValidationException::withMessages(['user' => 'Нельзя удалить последнего ГЛ.']);
                }
            }

            foreach (['activities', 'activity_loot', 'treasury_item_transactions', 'treasury_transactions', 'auctions', 'payouts', 'loot_imports'] as $table) {
                if (DB::table($table)->where('created_by', $lockedUser->id)->exists()) {
                    throw ValidationException::withMessages([
                        'user' => 'Пользователь уже проводил операции. Его нельзя удалить, чтобы не повредить историю; отвяжите от него персонажа.',
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
