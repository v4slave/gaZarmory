<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Services\UserRoleManager;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class UserRoleManagerTest extends TestCase
{
    public function test_last_guild_leader_cannot_be_demoted(): void
    {
        $this->expectException(ValidationException::class);

        (new UserRoleManager())->ensureLeaderRemains(
            true,
            false,
            1,
        );
    }

    public function test_guild_leader_can_be_demoted_when_another_leader_exists(): void
    {
        (new UserRoleManager())->ensureLeaderRemains(
            true,
            false,
            2,
        );

        $this->addToAssertionCount(1);
    }

    public function test_non_leader_role_can_be_changed(): void
    {
        (new UserRoleManager())->ensureLeaderRemains(
            false,
            false,
            1,
        );

        $this->addToAssertionCount(1);
    }
}
