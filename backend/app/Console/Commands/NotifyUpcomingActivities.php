<?php

namespace App\Console\Commands;

use App\Jobs\SendDiscordNotification;
use App\Models\Activity;
use App\Models\ActivityDefinition;
use App\Models\ArmoryNotification;
use App\Services\ArmoryNotificationService;
use App\Support\DiscordPrimeCard;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

final class NotifyUpcomingActivities extends Command
{
    protected $signature = 'activities:notify-upcoming';
    protected $description = 'Notify members about activities starting within 20 minutes';

    public function handle(ArmoryNotificationService $notifications): int
    {
        $now = CarbonImmutable::now('Europe/Moscow');
        $windowEnd = $now->addMinutes(20);
        $activities = Activity::query()
            ->whereNull('completed_at')
            ->whereBetween('occurred_at', [$now, $windowEnd])
            ->with('definition:id,name,icon_path')
            ->get();
        $members = $notifications->activeMembers();
        $scheduled = $this->scheduledEventsBetween($now, $windowEnd);
        $scheduledSignatures = $scheduled->pluck('signature')->all();
        $definitions = ActivityDefinition::query()
            ->get(['id', 'name', 'icon_path'])
            ->keyBy(fn (ActivityDefinition $definition): string => mb_strtolower($definition->name));

        foreach ($scheduled as $event) {
            $activity = $activities->first(fn (Activity $candidate): bool =>
                $this->signature($candidate->definition->name, CarbonImmutable::instance($candidate->occurred_at)) === $event['signature']
            );
            $this->notify(
                $notifications,
                $members,
                $event['name'],
                $event['starts_at'],
                $event['dedupe_key'],
                $activity,
                $definitions->get(mb_strtolower($event['name']))?->icon_url,
            );
        }

        foreach ($activities as $activity) {
            $startsAt = CarbonImmutable::instance($activity->occurred_at)->setTimezone('Europe/Moscow');
            if (in_array($this->signature($activity->definition->name, $startsAt), $scheduledSignatures, true)) {
                continue;
            }

            $this->notify(
                $notifications,
                $members,
                $activity->definition->name,
                $startsAt,
                'activity-upcoming-'.$activity->id,
                $activity,
                $activity->definition->icon_url,
            );
        }

        return self::SUCCESS;
    }

    private function notify(
        ArmoryNotificationService $notifications,
        Collection $members,
        string $name,
        CarbonImmutable $startsAt,
        string $dedupeKey,
        ?Activity $activity,
        ?string $iconUrl,
    ): void {
        $alreadyNotified = ArmoryNotification::query()
            ->where('dedupe_key', $dedupeKey)
            ->exists();
        $message = $name.' начнётся '.$startsAt->format('d.m.Y H:i').'.';

        $notifications->notify(
            $members,
            'activity_upcoming',
            'Событие скоро начнётся',
            $message,
            $activity ? '/activities/'.$activity->id : '/',
            $dedupeKey,
        );

        if (!$alreadyNotified && $members->isNotEmpty() && $this->shouldNotifyDiscord($name)) {
            SendDiscordNotification::dispatch(
                'Прайм · '.$name,
                'Сбор участников начинается. Не опаздывайте!',
                'gold',
                'primes',
                DiscordPrimeCard::scheduled(
                    $name,
                    $startsAt,
                    $iconUrl,
                    $activity?->id,
                ),
            );
        }
    }

    private function scheduledEventsBetween(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $daily = config('guild_schedule.daily', []);
        $weekly = config('guild_schedule.weekly', []);
        $events = collect();

        for ($day = $from->startOfDay(); $day->lessThanOrEqualTo($to->startOfDay()); $day = $day->addDay()) {
            $slots = [...$daily, ...($weekly[$day->dayOfWeekIso] ?? [])];

            foreach ($slots as [$time, $name]) {
                $startsAt = $day->setTimeFromTimeString($time);
                if ($startsAt->lessThan($from) || $startsAt->greaterThan($to)) {
                    continue;
                }

                $signature = $this->signature($name, $startsAt);
                $events->push([
                    'name' => $name,
                    'starts_at' => $startsAt,
                    'signature' => $signature,
                    'dedupe_key' => 'schedule-upcoming-'.sha1($signature),
                ]);
            }
        }

        return $events;
    }

    private function signature(string $name, CarbonImmutable $startsAt): string
    {
        return mb_strtolower($name).'|'.$startsAt->timestamp;
    }

    private function shouldNotifyDiscord(string $name): bool
    {
        $allowedNames = collect(config('guild_schedule.discord_notifications', []))
            ->map(fn (string $allowedName): string => mb_strtolower(trim($allowedName)));

        return $allowedNames->contains(mb_strtolower(trim($name)));
    }
}
