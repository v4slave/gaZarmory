<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\GuildGroup;
use App\Models\PartySquad;
use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class PartySquadController extends Controller
{
    public function show(Request $request, GuildGroup $group)
    {
        $this->ensureViewAccess($request);
        $periodStart = now()->subDays(30);
        $totalPrimes = Activity::query()->where('occurred_at', '>=', $periodStart)
            ->whereHas('definition', fn ($query) => $query->where('type', 'prime'))->count();
        $players = $group->players()->where('is_active', true)
            ->select(['players.id','user_id','group_id','nickname','class','gear_score'])
            ->with('user:id,discord_id,discord_username,discord_display_name,discord_avatar,role,roles')
            ->withCount(['activities as primes_count' => fn ($query) => $query->where('occurred_at', '>=', $periodStart)
                ->whereHas('definition', fn ($definition) => $definition->where('type', 'prime'))])
            ->orderBy('nickname')->get()
            ->each(fn (Player $player) => $player->setAttribute('attendance_percentage', $totalPrimes > 0 ? round($player->primes_count / $totalPrimes * 100, 1) : 0));

        $squads = $group->squads()->with('players:id')->get()->map(fn (PartySquad $squad) => [
            'id' => $squad->id, 'name' => $squad->name, 'position' => $squad->position,
            'player_ids' => $squad->players->pluck('id')->values(),
        ]);

        return response()->json(['group' => $group->only(['id','name']), 'players' => $players, 'squads' => $squads, 'can_edit' => $this->canEdit($request, $group)]);
    }

    public function store(Request $request, GuildGroup $group)
    {
        $this->ensureEditAccess($request, $group);
        $data = $request->validate(['name' => ['nullable','string','max:120']]);
        $position = (int) $group->squads()->max('position') + 1;
        $name = trim($data['name'] ?? '') ?: 'Группа '.$position;
        $squad = $group->squads()->create(['name' => $name, 'position' => $position]);
        return response()->json($squad, 201);
    }

    public function update(Request $request, GuildGroup $group, PartySquad $squad)
    {
        $this->ensureSquad($request, $group, $squad);
        $data = $request->validate(['name' => ['required','string','max:120', Rule::unique('party_squads')->where('group_id', $group->id)->ignore($squad)]]);
        $squad->update($data);
        return $squad->refresh();
    }

    public function destroy(Request $request, GuildGroup $group, PartySquad $squad)
    {
        $this->ensureSquad($request, $group, $squad);
        $squad->delete();
        return response()->json(null, 204);
    }

    public function assign(Request $request, GuildGroup $group, PartySquad $squad)
    {
        $this->ensureSquad($request, $group, $squad);
        $data = $request->validate(['player_id' => ['required','integer','exists:players,id']]);
        $player = Player::query()->findOrFail($data['player_id']);
        if ($player->group_id !== $group->id || !$player->is_active) throw ValidationException::withMessages(['player_id' => __('domain.party.player_not_member')]);
        DB::transaction(function () use ($squad, $player): void {
            $locked = PartySquad::query()->whereKey($squad)->lockForUpdate()->firstOrFail();
            if (!$locked->players()->whereKey($player)->exists() && $locked->players()->count() >= 5) {
                throw ValidationException::withMessages(['player_id' => __('domain.party.squad_full')]);
            }
            DB::table('party_squad_players')->where('player_id', $player->id)->delete();
            $locked->players()->attach($player->id);
        });
        return response()->json(null, 204);
    }

    public function unassign(Request $request, GuildGroup $group, Player $player)
    {
        $this->ensureEditAccess($request, $group);
        abort_unless($player->group_id === $group->id, 404);
        DB::table('party_squad_players')->where('player_id', $player->id)->delete();
        return response()->json(null, 204);
    }

    private function ensureSquad(Request $request, GuildGroup $group, PartySquad $squad): void
    {
        $this->ensureEditAccess($request, $group);
        abort_unless($squad->group_id === $group->id, 404);
    }

    private function ensureViewAccess(Request $request): void
    {
        abort_unless($request->user()?->player, 403);
    }

    private function ensureEditAccess(Request $request, GuildGroup $group): void
    {
        abort_unless($this->canEdit($request, $group), 403);
    }

    private function canEdit(Request $request, GuildGroup $group): bool
    {
        $user = $request->user();
        return $user->canManageGuild() || ($user->hasRole(UserRole::PartyLeader) && $user->player?->group_id === $group->id);
    }
}
