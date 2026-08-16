<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['discord_id', 'discord_username', 'discord_display_name', 'discord_avatar'];
    protected $hidden = [];
    protected function casts(): array { return ['role' => UserRole::class, 'roles' => 'array']; }
    public function player(): HasOne { return $this->hasOne(Player::class); }

    public function hasRole(UserRole|string $role): bool
    {
        $value = $role instanceof UserRole ? $role->value : $role;
        $roles = $this->roles ?: [$this->role->value];
        return in_array($value, $roles, true);
    }

    public function canManageGuild(): bool
    {
        return $this->hasRole(UserRole::GuildLeader)
            || $this->hasRole(UserRole::Developer);
    }

    public function canAdministrate(): bool
    {
        return $this->hasRole(UserRole::GuildLeader) || $this->hasRole(UserRole::Developer);
    }

    public static function primaryRoleFor(array $roles): UserRole
    {
        foreach ([UserRole::GuildLeader, UserRole::Developer, UserRole::PartyLeader, UserRole::Member] as $role) {
            if (in_array($role->value, $roles, true)) return $role;
        }
        return UserRole::Member;
    }
}
