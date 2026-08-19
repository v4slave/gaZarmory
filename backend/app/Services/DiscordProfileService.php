<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;

final class DiscordProfileService
{
    public function sync(User $user, bool $force = false): User
    {
        if (!$user->discord_refresh_token && !$user->discord_access_token) return $user;
        if (!$force && $user->discord_synced_at?->isAfter(now()->subHours(6))) return $user;

        try {
            if (!$user->discord_access_token || $user->discord_token_expires_at?->isBefore(now()->addMinute())) {
                if (!$this->refreshToken($user)) return $user;
            }

            $profile = Http::acceptJson()
                ->withToken($user->discord_access_token)
                ->timeout(8)
                ->get('https://discord.com/api/v10/users/@me')
                ->throw()
                ->json();

            $user->forceFill([
                'discord_username' => $profile['username'] ?? $user->discord_username,
                'discord_display_name' => $profile['global_name'] ?? $profile['username'] ?? $user->discord_display_name,
                'discord_avatar' => $profile['avatar'] ?? null,
                'discord_synced_at' => now(),
            ])->save();
        } catch (\Throwable) {
            // A temporary Discord outage must not interrupt the authenticated session.
        }

        return $user->refresh();
    }

    private function refreshToken(User $user): bool
    {
        if (!$user->discord_refresh_token) return false;

        $token = Http::asForm()->acceptJson()->timeout(8)
            ->post('https://discord.com/api/v10/oauth2/token', [
                'client_id' => config('services.discord.client_id'),
                'client_secret' => config('services.discord.client_secret'),
                'grant_type' => 'refresh_token',
                'refresh_token' => $user->discord_refresh_token,
            ])->throw()->json();

        $user->forceFill([
            'discord_access_token' => $token['access_token'],
            'discord_refresh_token' => $token['refresh_token'] ?? $user->discord_refresh_token,
            'discord_token_expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 604800)),
        ])->save();

        return true;
    }
}
