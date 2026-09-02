<?php

namespace Tests\Unit;

use App\Models\Auction;
use App\Models\TreasuryItem;
use App\Support\DiscordAuctionCard;
use Carbon\CarbonImmutable;
use Tests\TestCase;

final class DiscordAuctionCardTest extends TestCase
{
    public function test_new_auction_mentions_the_member_role(): void
    {
        config(['services.discord.member_role_id' => '123456789012345678']);
        $item = new TreasuryItem(['item_name' => 'Тестовый предмет']);
        $auction = new Auction([
            'starting_bid' => 150,
            'quantity' => 1,
            'ends_at' => now()->addHour(),
        ]);
        $auction->id = 1;

        $card = DiscordAuctionCard::active($auction, $item);

        self::assertSame('123456789012345678', $card['mention_role_id']);
    }

    public function test_auction_time_uses_a_real_line_break(): void
    {
        $endsAt = CarbonImmutable::parse('2026-09-02 15:24:00', 'Europe/Moscow');
        $item = new TreasuryItem(['item_name' => 'Тестовый предмет']);
        $auction = new Auction([
            'starting_bid' => 150,
            'quantity' => 1,
            'ends_at' => $endsAt,
        ]);
        $auction->id = 1;
        $auction->setRelation('item', $item);

        $card = DiscordAuctionCard::outbid($auction, 155);
        $time = $card['fields'][1]['value'];

        self::assertStringContainsString("\n", $time);
        self::assertStringNotContainsString('\\n', $time);
        self::assertSame("<t:{$endsAt->timestamp}:F>\n<t:{$endsAt->timestamp}:R>", $time);
    }
}
