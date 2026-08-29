<?php

namespace App\Console\Commands;

use App\Jobs\SendDiscordNotification;
use App\Models\Activity;
use App\Models\ArmoryNotification;
use App\Services\ArmoryNotificationService;
use App\Support\DiscordPrimeCard;
use Illuminate\Console\Command;

final class NotifyUpcomingActivities extends Command
{
    protected $signature = 'activities:notify-upcoming';
    protected $description = 'Notify members about activities starting within 30 minutes';

    public function handle(ArmoryNotificationService $notifications): int
    {
        $activities = Activity::query()
            ->whereNull('completed_at')
            ->whereBetween('occurred_at', [now(), now()->addMinutes(30)])
            ->with('definition:id,name,icon_path')
            ->get();
        $members = $notifications->activeMembers();

        foreach ($activities as $activity) {
            $dedupeKey = 'activity-upcoming-'.$activity->id;
            $alreadyNotified = ArmoryNotification::query()
                ->where('dedupe_key', $dedupeKey)
                ->exists();
            $message = $activity->definition->name.' начнётся '.$activity->occurred_at->format('d.m.Y H:i').'.';

            $notifications->notify(
                $members,
                'activity_upcoming',
                'Событие скоро начнётся',
                $message,
                '/activities/'.$activity->id,
                $dedupeKey,
            );

            if (!$alreadyNotified && $members->isNotEmpty()) {
                SendDiscordNotification::dispatch(
                    'Прайм · '.$activity->definition->name,
                    'Сбор участников начинается. Не опаздывайте!',
                    'gold',
                    'primes',
                    DiscordPrimeCard::upcoming($activity),
                );
            }
        }

        return self::SUCCESS;
    }
}
