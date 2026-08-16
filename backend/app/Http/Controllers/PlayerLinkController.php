<?php

namespace App\Http\Controllers;

use App\Actions\LinkDiscordUserToPlayer;
use App\Models\Player;
use Illuminate\Http\Request;

final class PlayerLinkController extends Controller
{
    public function __invoke(Request $request, Player $player, LinkDiscordUserToPlayer $action): Player
    {
        $this->authorize('linkUser', $player);
        $data = $request->validate(['user_id' => ['nullable', 'integer', 'exists:users,id']]);
        return $action->execute($player, $data['user_id'] ?? null);
    }
}

