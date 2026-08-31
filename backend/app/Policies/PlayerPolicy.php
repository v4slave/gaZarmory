<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Player;
use App\Models\User;

final class PlayerPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, Player $player): bool { return true; }
    public function create(User $user): bool
    {
        return $user->canManageGuild()
            || ($user->hasRole(UserRole::PartyLeader) && $user->player?->group_id !== null);
    }
    public function update(User $user, Player $player): bool { return $user->canManageGuild(); }
    public function move(User $user, Player $player): bool
    {
        if ($user->canManageGuild()) return true;
        $ownGroupId = $user->player?->group_id;
        return $user->hasRole(UserRole::PartyLeader)
            && $ownGroupId !== null
            && ($player->group_id === null || $player->group_id === $ownGroupId);
    }
    public function delete(User $user, Player $player): bool { return $user->canManageGuild(); }
    public function linkUser(User $user, Player $player): bool { return $user->canAdministrate(); }
}
