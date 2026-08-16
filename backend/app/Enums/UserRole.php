<?php

namespace App\Enums;

enum UserRole: string
{
    case GuildLeader = 'guild_leader';
    case Developer = 'developer';
    case PartyLeader = 'party_leader';
    case Member = 'member';

    public function canManageGuild(): bool
    {
        return $this === self::GuildLeader || $this === self::Developer;
    }
}
