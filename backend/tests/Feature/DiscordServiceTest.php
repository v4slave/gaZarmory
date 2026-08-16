<?php

namespace Tests\Feature;

use App\Services\DiscordService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class DiscordServiceTest extends TestCase
{
    public function test_it_skips_delivery_when_webhook_is_not_configured(): void
    {
        config(['services.discord.webhook_url' => null]);
        Http::fake();

        self::assertFalse(app(DiscordService::class)->send('Заголовок', 'Сообщение'));
        Http::assertNothingSent();
    }

    public function test_it_sends_safe_discord_embed(): void
    {
        config(['services.discord.webhook_url' => 'https://discord.test/webhook']);
        Http::fake(['discord.test/*' => Http::response(status: 204)]);

        self::assertTrue(app(DiscordService::class)->send('Аукцион', 'Новая ставка'));
        Http::assertSent(fn ($request) =>
            $request->url() === 'https://discord.test/webhook'
            && $request['allowed_mentions']['parse'] === []
            && $request['embeds'][0]['title'] === 'Аукцион'
        );
    }
}
