<?php

namespace App\Http\Controllers;

use App\Actions\LinkDiscordUserToPlayer;
use App\Models\PlayerLinkRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PlayerLinkRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdministrator($request);

        return response()->json(PlayerLinkRequest::query()
            ->where('status', 'pending')
            ->with(['user:id,discord_id,discord_username,discord_display_name,discord_avatar', 'player:id,nickname,class,group_id'])
            ->latest('id')
            ->get());
    }

    public function approve(Request $request, PlayerLinkRequest $playerLinkRequest, LinkDiscordUserToPlayer $link, AuditService $audit): JsonResponse
    {
        $this->authorizeAdministrator($request);

        $approved = DB::transaction(function () use ($request, $playerLinkRequest, $link, $audit): PlayerLinkRequest {
            $locked = PlayerLinkRequest::query()->lockForUpdate()->findOrFail($playerLinkRequest->id);
            if ($locked->status !== 'pending') throw ValidationException::withMessages(['request' => 'Заявка уже обработана.']);
            $user = User::query()->lockForUpdate()->findOrFail($locked->user_id);
            if ($user->player()->exists()) throw ValidationException::withMessages(['request' => 'Пользователь уже привязан к другому персонажу.']);

            $link->execute($locked->player, $locked->user_id, true);
            $locked->update(['status' => 'approved', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            $audit->record('player_link_request.approved', $locked, ['status' => 'pending'], ['status' => 'approved', 'player_id' => $locked->player_id, 'user_id' => $locked->user_id]);

            return $locked;
        });

        return response()->json($approved->load(['user', 'player']));
    }

    public function reject(Request $request, PlayerLinkRequest $playerLinkRequest, AuditService $audit): JsonResponse
    {
        $this->authorizeAdministrator($request);

        $rejected = DB::transaction(function () use ($request, $playerLinkRequest, $audit): PlayerLinkRequest {
            $locked = PlayerLinkRequest::query()->lockForUpdate()->findOrFail($playerLinkRequest->id);
            if ($locked->status !== 'pending') throw ValidationException::withMessages(['request' => 'Заявка уже обработана.']);
            $locked->update(['status' => 'rejected', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            $audit->record('player_link_request.rejected', $locked, ['status' => 'pending'], ['status' => 'rejected', 'player_id' => $locked->player_id, 'user_id' => $locked->user_id]);

            return $locked;
        });

        return response()->json($rejected->load(['user', 'player']));
    }

    private function authorizeAdministrator(Request $request): void
    {
        abort_unless($request->user()?->canAdministrate(), 403);
    }
}
