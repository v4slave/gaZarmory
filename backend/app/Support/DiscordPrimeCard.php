<?php

namespace App\Support;

use App\Models\Activity;
use Carbon\CarbonImmutable;

final class DiscordPrimeCard
{
    public static function upcoming(Activity $activity): array
    {
        return self::scheduled(
            $activity->definition->name,
            CarbonImmutable::instance($activity->occurred_at),
            $activity->definition->icon_url,
            $activity->id,
        );
    }

    public static function scheduled(
        string $name,
        CarbonImmutable $startsAt,
        ?string $iconUrl = null,
        ?int $activityId = null,
    ): array {
        $timestamp = $startsAt->timestamp;
        $frontendUrl = rtrim(config('app.frontend_url'), '/');

        return [
            'url' => $activityId ? $frontendUrl.'/activities/'.$activityId : $frontendUrl,
            'thumbnail_url' => $iconUrl ? strtok($iconUrl, '#') : null,
            'fields' => [
                ['name' => 'СТАТУС', 'value' => '**Скоро начнётся**', 'inline' => true],
                ['name' => 'ТИП СОБЫТИЯ', 'value' => '**Прайм**', 'inline' => true],
                ['name' => 'НАЧАЛО', 'value' => '<t:'.$timestamp.':F>\n<t:'.$timestamp.':R>', 'inline' => false],
            ],
            'footer' => $activityId
                ? 'GAZ ARMORY · Прайм #'.$activityId.' · Нажмите на заголовок, чтобы открыть событие'
                : 'GAZ ARMORY · Расписание праймов',
            'mention_role_id' => config('services.discord.member_role_id'),
        ];
    }
}
