<?php

namespace App\Enums;

enum UserRole: string
{
    case GuildLeader = 'guild_leader';
    case MicroGuildLeader = 'micro_guild_leader';
    case Developer = 'developer';
    case PartyLeader = 'party_leader';
    case Member = 'member';

    public function canManageGuild(): bool
    {
        return in_array($this, [self::GuildLeader, self::MicroGuildLeader, self::Developer], true);
    }
}
