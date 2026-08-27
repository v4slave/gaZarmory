<?php

namespace Tests\Unit;

use App\Http\Middleware\SetRequestLocale;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SetRequestLocaleTest extends TestCase
{
    public function test_it_uses_a_supported_accept_language(): void
    {
        $request = Request::create('/api/test', server: ['HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9']);

        (new SetRequestLocale)->handle($request, fn () => new Response);

        $this->assertSame('en', app()->getLocale());
        $this->assertSame('The lot is not active.', __('domain.auction.inactive'));
        $this->assertSame('No game profile is linked.', __('domain.profile.not_linked'));
    }

    public function test_it_falls_back_to_russian(): void
    {
        $request = Request::create('/api/test', server: ['HTTP_ACCEPT_LANGUAGE' => 'de-DE']);

        (new SetRequestLocale)->handle($request, fn () => new Response);

        $this->assertSame('ru', app()->getLocale());
    }
}
