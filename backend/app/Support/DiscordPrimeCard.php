<?php

namespace App\Support;

use App\Models\Activity;

final class DiscordPrimeCard
{
    public static function upcoming(Activity $activity): array
    {
        $timestamp = $activity->occurred_at->timestamp;
        $iconUrl = $activity->definition->icon_url;

        return [
            'url' => rtrim(config('app.frontend_url'), '/').'/activities/'.$activity->id,
            'thumbnail_url' => $iconUrl ? strtok($iconUrl, '#') : null,
            'fields' => [
                ['name' => 'СТАТУС', 'value' => '**Скоро начнётся**', 'inline' => true],
                ['name' => 'ТИП СОБЫТИЯ', 'value' => '**Прайм**', 'inline' => true],
                ['name' => 'НАЧАЛО', 'value' => '<t:'.$timestamp.':F>\n<t:'.$timestamp.':R>', 'inline' => false],
            ],
            'footer' => 'GAZ ARMORY · Прайм #'.$activity->id.' · Нажмите на заголовок, чтобы открыть событие',
            'mention_role_id' => config('services.discord.member_role_id'),
        ];
    }
}
