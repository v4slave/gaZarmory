<?php

namespace App\Actions;

use App\Models\Player;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LinkDiscordUserToPlayer
{
    public function __construct(private readonly AuditService $audit) {}

    public function execute(Player $player, ?int $userId, bool $requireUnlinked = false): Player
    {
        return DB::transaction(function () use ($player, $userId, $requireUnlinked): Player {
            $locked = Player::query()->lockForUpdate()->findOrFail($player->id);
            if ($requireUnlinked && $locked->user_id !== null) {
                throw ValidationException::withMessages(['player_id' => 'Этот профиль уже занят.']);
            }
            $old = ['user_id' => $locked->user_id];
            if ($userId !== null) {
                User::query()->lockForUpdate()->findOrFail($userId);
                if (Player::query()->where('user_id', $userId)->whereKeyNot($locked->id)->exists()) {
                    throw ValidationException::withMessages(['user_id' => 'Этот Discord User уже связан с другим игроком.']);
                }
            }
            $locked->user_id = $userId;
            $locked->save();
            $this->audit->record('player.discord_link_changed', $locked, $old, ['user_id' => $userId]);
            return $locked->refresh()->load('user');
        });
    }
}
