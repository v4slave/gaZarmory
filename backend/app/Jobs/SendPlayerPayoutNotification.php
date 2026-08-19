<?php

namespace App\Jobs;

use App\Services\DiscordService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SendPlayerPayoutNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 3;
    public array $backoff = [10,60,180];
    public function __construct(public readonly string $discordUserId, public readonly string $title, public readonly string $message) {}
    public function handle(DiscordService $discord): void { $discord->sendToUser($this->discordUserId,$this->title,$this->message); }
}
