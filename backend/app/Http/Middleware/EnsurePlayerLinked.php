<?php

namespace App\Http\Middleware;

use App\Models\Player;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePlayerLinked
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user?->canAdministrate()) return $next($request);

        $userId = $user?->getAuthIdentifier();
        abort_unless($userId && Player::query()->where('user_id', $userId)->exists(), 403, __('domain.profile.link_required'));

        return $next($request);
    }
}
