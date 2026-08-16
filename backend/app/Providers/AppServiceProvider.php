<?php

namespace App\Providers;

use App\Models\GuildGroup;
use App\Models\Activity;
use App\Models\Player;
use App\Policies\GuildGroupPolicy;
use App\Policies\ActivityPolicy;
use App\Policies\PlayerPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Discord\DiscordExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Event::listen(SocialiteWasCalled::class, DiscordExtendSocialite::class.'@handle');
        Gate::policy(Player::class, PlayerPolicy::class);
        Gate::policy(GuildGroup::class, GuildGroupPolicy::class);
        Gate::policy(Activity::class, ActivityPolicy::class);

        RateLimiter::for('api', fn (Request $request) =>
            Limit::perMinute(60)->by($request->user()?->id ?: $request->ip())
        );
    }
}
