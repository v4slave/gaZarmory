<?php

namespace App\Http\Controllers;

use App\Enums\PlayerClass;
use App\Enums\UserRole;
use App\Models\GuildGroup;
use App\Models\Player;
use App\Models\PlayerGearScoreHistory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class RosterReadinessController extends Controller
{
    private const ASSETS = ['has_ship','has_tank','has_fuchsias','has_clouds','has_machaon','has_tare','has_deer','has_invulnerable_pet','has_shield_swap','has_flippers'];

    public function __invoke(Request $request)
    {
        $user = $request->user();
        abort_unless($user->canManageGuild() || $user->hasRole(UserRole::PartyLeader), 403);

        $data = $request->validate([
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'class' => ['nullable', Rule::enum(PlayerClass::class)],
            'search' => ['nullable', 'string', 'max:120'],
            'min_gear_score' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'max_gear_score' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'missing_asset' => ['nullable', Rule::in(self::ASSETS)],
        ]);

        $partyGroupId = $user->hasRole(UserRole::PartyLeader) && !$user->canManageGuild()
            ? $user->player?->group_id
            : null;
        if ($user->hasRole(UserRole::PartyLeader) && !$user->canManageGuild()) abort_unless($partyGroupId, 403, 'PL не привязан к конст-пати.');

        $query = Player::query()->where('is_active', true)->with(['group:id,name', 'user:id,discord_id,discord_username,discord_display_name,discord_avatar,role,roles']);
        if ($partyGroupId) $query->where('group_id', $partyGroupId);
        elseif (!empty($data['group_id'])) $query->where('group_id', $data['group_id']);
        if (!empty($data['class'])) $query->where('class', $data['class']);
        if (!empty($data['search'])) $query->where('nickname', 'ilike', '%'.str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $data['search']).'%');
        if (isset($data['min_gear_score'])) $query->where('gear_score', '>=', $data['min_gear_score']);
        if (isset($data['max_gear_score'])) $query->where('gear_score', '<=', $data['max_gear_score']);
        if (!empty($data['missing_asset'])) $query->where($data['missing_asset'], false);

        $players = $query->orderByDesc('gear_score')->orderBy('nickname')->get();
        $history = PlayerGearScoreHistory::query()
            ->whereIn('player_id', $players->modelKeys())
            ->where('recorded_at', '<=', now())
            ->orderBy('recorded_at')
            ->get()
            ->groupBy('player_id');
        $week = now()->subWeek();
        $month = now()->subMonth();

        $players->each(function (Player $player) use ($history, $week, $month): void {
            $rows = $history->get($player->id, collect());
            $scoreAt = fn ($date) => (int) ($rows->where('recorded_at', '<=', $date)->last()?->gear_score ?? $rows->first()?->gear_score ?? $player->gear_score);
            $player->setAttribute('gear_score_week_delta', $player->gear_score - $scoreAt($week));
            $player->setAttribute('gear_score_month_delta', $player->gear_score - $scoreAt($month));
        });

        $groups = GuildGroup::query()->when($partyGroupId, fn ($q) => $q->whereKey($partyGroupId))->orderBy('name')->get(['id','name']);
        return response()->json([
            'players' => $players,
            'groups' => $groups,
            'summary' => [
                'players' => $players->count(),
                'average_gear_score' => (int) round($players->avg('gear_score') ?? 0),
                'ready' => $players->filter(fn ($player) => collect(self::ASSETS)->every(fn ($asset) => $player->{$asset}))->count(),
            ],
        ]);
    }
}
