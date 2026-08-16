<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\GuildGroup;
use App\Models\User;

final class GuildGroupPolicy
{
    public function viewAny(User $user): bool { return true; }
    public function view(User $user, GuildGroup $group): bool { return true; }
    public function create(User $user): bool { return $user->canManageGuild(); }
    public function update(User $user, GuildGroup $group): bool { return $user->canManageGuild() || $this->leads($user, $group); }
    public function delete(User $user, GuildGroup $group): bool { return $user->canManageGuild() || $this->leads($user, $group); }

    private function leads(User $user, GuildGroup $group): bool
    {
        return $user->hasRole(UserRole::PartyLeader)
            && $user->player?->group_id === $group->id;
    }
}
