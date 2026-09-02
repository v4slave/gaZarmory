<?php

namespace App\Support;

use App\Models\Auction;
use App\Models\TreasuryItem;

final class DiscordAuctionCard
{
    public static function active(Auction $auction, TreasuryItem $item): array
    {
        return self::base($auction, $item, [
            self::field('СТАТУС', '**Активен**'),
            self::field('КОЛИЧЕСТВО', $auction->quantity.' шт.'),
            self::field('СТАРТОВАЯ ЦЕНА', self::tokens((int) $auction->starting_bid)),
            self::field('ЗАВЕРШЕНИЕ', self::discordTime($auction->ends_at->timestamp), false),
        ]) + ['mention_role_id' => config('services.discord.member_role_id')];
    }

    public static function finished(Auction $auction, TreasuryItem $item, ?string $winner, ?int $winningBid): array
    {
        $fields = [
            self::field('СТАТУС', '**Завершён**'),
            self::field('КОЛИЧЕСТВО', $auction->quantity.' шт.'),
            self::field('ЦЕНА ВЫКУПА', $winningBid === null ? 'Ставок не было' : self::tokens($winningBid)),
        ];
        if ($winner !== null) $fields[] = self::field('ПОБЕДИТЕЛЬ', '**'.$winner.'**', false);

        return self::base($auction, $item, $fields);
    }

    public static function outbid(Auction $auction, int $currentBid): array
    {
        return self::base($auction, $auction->item, [
            self::field('ТЕКУЩАЯ ЦЕНА', self::tokens($currentBid)),
            self::field('ЗАВЕРШЕНИЕ', self::discordTime($auction->ends_at->timestamp)),
        ]);
    }

    private static function base(Auction $auction, TreasuryItem $item, array $fields): array
    {
        return [
            'url' => rtrim(config('app.frontend_url'), '/').'/auctions/'.$auction->id,
            'thumbnail_url' => $item->icon_url ? strtok($item->icon_url, '#') : null,
            'fields' => $fields,
            'footer' => 'GAZ ARMORY · Аукцион #'.$auction->id.' · Нажмите на заголовок, чтобы открыть лот',
        ];
    }

    private static function field(string $name, string $value, bool $inline = true): array
    {
        return compact('name', 'value', 'inline');
    }

    private static function tokens(int $amount): string
    {
        return '**'.number_format($amount, 0, '', ' ').' жетонов**';
    }

    private static function discordTime(int $timestamp): string
    {
        return '<t:'.$timestamp.":F>\n<t:".$timestamp.':R>';
    }
}
