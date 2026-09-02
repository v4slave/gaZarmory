<?php

namespace Tests\Unit;

use App\Support\DiscordPayoutCard;
use Tests\TestCase;

final class DiscordPayoutCardTest extends TestCase
{
    public function test_paid_payout_mentions_the_member_role_and_summarizes_recipients(): void
    {
        config(['services.discord.member_role_id' => '123456789012345678']);

        $card = DiscordPayoutCard::paid(42, 1500, 3, ['АГЛ' => 1500]);

        self::assertSame('123456789012345678', $card['mention_role_id']);
        self::assertSame('**1 500 золота**', $card['fields'][0]['value']);
        self::assertSame('**3**', $card['fields'][1]['value']);
    }
}
