<?php

namespace App\Support;

final class DiscordPayoutCard
{
    public static function paid(int $payoutId, int $amount, int $playerCount, array $breakdown): array
    {
        $fields = [
            [
                'name' => 'ВЫДАНО',
                'value' => '**'.number_format($amount, 0, '', ' ').' золота**',
                'inline' => true,
            ],
            [
                'name' => 'ПОЛУЧАТЕЛЕЙ',
                'value' => '**'.$playerCount.'**',
                'inline' => true,
            ],
        ];

        foreach (array_slice($breakdown, 0, 24, true) as $activity => $share) {
            $fields[] = [
                'name' => mb_strtoupper((string) $activity),
                'value' => number_format((int) $share, 0, '', ' ').' золота',
                'inline' => true,
            ];
        }

        return [
            'url' => rtrim(config('app.frontend_url'), '/').'/payouts/'.$payoutId,
            'fields' => $fields,
            'footer' => 'GAZ ARMORY · Выплата #'.$payoutId.' · Нажмите на заголовок, чтобы открыть расчёт',
            'mention_role_id' => config('services.discord.member_role_id'),
        ];
    }
}
