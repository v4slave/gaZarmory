<?php

namespace App\Http\Controllers;

use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

final class AuthController
{
    public function redirect(): SymfonyRedirectResponse
    {
        return $this->discord()->scopes(['identify'])->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $discord = $this->discord()->user();
        $user = User::query()->updateOrCreate(
            ['discord_id' => (string) $discord->getId()],
            [
                'discord_username' => $discord->getNickname() ?: $discord->getName() ?: (string) $discord->getId(),
                'discord_display_name' => $discord->getName(),
                'discord_avatar' => $discord->getAvatar(),
            ],
        );

        // Discord is the only sign-in method, so remember successful logins across
        // browser restarts until the user explicitly signs out.
        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->away(rtrim(config('app.frontend_url', env('FRONTEND_URL')), '/').'/dashboard');
    }

    private function discord(): mixed
    {
        $provider = Socialite::driver('discord');
        $provider->setHttpClient(new Client([
            'verify' => storage_path('certs/cacert.pem'),
            'timeout' => 10,
        ]));

        return $provider;
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->noContent();
    }
}
