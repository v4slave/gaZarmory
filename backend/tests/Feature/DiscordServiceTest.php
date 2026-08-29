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

    public function test_it_routes_messages_to_a_channel_webhook_with_default_fallback(): void
    {
        config([
            'services.discord.webhook_url' => 'https://discord.test/default',
            'services.discord.webhook_urls.auctions' => 'https://discord.test/auctions',
        ]);
        Http::fake(['discord.test/*' => Http::response(status: 204)]);

        app(DiscordService::class)->send('Аукцион', 'Новый лот', 'gold', 'auctions');
        app(DiscordService::class)->send('Прайм', 'Скоро начало', 'gold', 'primes');

        Http::assertSent(fn ($request) => $request->url() === 'https://discord.test/auctions');
        Http::assertSent(fn ($request) => $request->url() === 'https://discord.test/default');
    }

    public function test_it_uses_a_distinct_username_for_each_notification_channel(): void
    {
        config([
            'services.discord.webhook_urls.auctions' => 'https://discord.test/auctions',
            'services.discord.webhook_urls.primes' => 'https://discord.test/primes',
            'services.discord.webhook_urls.payouts' => 'https://discord.test/payouts',
        ]);
        Http::fake(['discord.test/*' => Http::response(status: 204)]);

        $discord = app(DiscordService::class);
        $discord->send('Аукцион', 'Новый лот', 'gold', 'auctions');
        $discord->send('Прайм', 'Скоро начало', 'gold', 'primes');
        $discord->sendToUser('123', 'Выплата', 'Золото выдано', 'payouts');

        Http::assertSent(fn ($request) =>
            $request->url() === 'https://discord.test/auctions'
            && $request['username'] === 'Рыжий аукционист'
        );
        Http::assertSent(fn ($request) =>
            $request->url() === 'https://discord.test/primes'
            && $request['username'] === 'Рыжий почтальон'
        );
        Http::assertSent(fn ($request) =>
            $request->url() === 'https://discord.test/payouts'
            && $request['username'] === 'Рыжий банкир'
        );
    }

    public function test_it_sends_a_rich_embed_card(): void
    {
        config(['services.discord.webhook_url' => 'https://discord.test/webhook']);
        Http::fake(['discord.test/*' => Http::response(status: 204)]);

        app(DiscordService::class)->send('Новый аукцион', 'Лот открыт.', 'gold', 'auctions', [
            'url' => 'https://gaz-army.ru/auctions/42',
            'thumbnail_url' => 'https://gaz-army.ru/storage/item.png',
            'fields' => [['name' => 'СТАРТОВАЯ ЦЕНА', 'value' => '**720 жетонов**', 'inline' => true]],
            'footer' => 'GAZ ARMORY · Аукцион #42',
        ]);

        Http::assertSent(fn ($request) =>
            $request['embeds'][0]['url'] === 'https://gaz-army.ru/auctions/42'
            && $request['embeds'][0]['thumbnail']['url'] === 'https://gaz-army.ru/storage/item.png'
            && $request['embeds'][0]['fields'][0]['value'] === '**720 жетонов**'
            && $request['embeds'][0]['footer']['text'] === 'GAZ ARMORY · Аукцион #42'
        );
    }

    public function test_it_mentions_only_the_configured_member_role(): void
    {
        config(['services.discord.webhook_url' => 'https://discord.test/webhook']);
        Http::fake(['discord.test/*' => Http::response(status: 204)]);

        app(DiscordService::class)->send('Прайм', 'Скоро начало', 'gold', 'primes', [
            'mention_role_id' => '123456789012345678',
        ]);

        Http::assertSent(fn ($request) =>
            $request['content'] === '<@&123456789012345678>'
            && $request['allowed_mentions'] === ['roles' => ['123456789012345678']]
        );
    }
}
