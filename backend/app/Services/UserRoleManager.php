<?php

namespace App\Services;

use App\Enums\UserRole;
use Illuminate\Validation\ValidationException;

final class UserRoleManager
{
    public function ensureLeaderRemains(bool $wasGuildLeader, bool $willBeGuildLeader, int $leadersCount): void
    {
        if ($wasGuildLeader && !$willBeGuildLeader && $leadersCount <= 1) {
            throw ValidationException::withMessages([
                'roles' => __('domain.admin.last_guild_leader'),
            ]);
        }
    }
}
