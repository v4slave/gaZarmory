<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

final class DiscordService
{
    public function send(string $title, string $message, string $color = 'gold', string $channel = 'default'): bool
    {
        $webhookUrl = $this->webhookUrl($channel);
        if (!$webhookUrl) return false;

        $colors = ['gold' => 0xC68A2D, 'green' => 0x3BA55D, 'red' => 0xED4245];
        Http::timeout(5)->retry(2, 250)->post($webhookUrl, [
            'username' => 'GAZ ARMORY',
            'allowed_mentions' => ['parse' => []],
            'embeds' => [[
                'title' => $title,
                'description' => $message,
                'color' => $colors[$color] ?? $colors['gold'],
                'timestamp' => now()->toIso8601String(),
            ]],
        ])->throw();

        return true;
    }

    public function sendToUser(string $discordUserId, string $title, string $message, string $channel = 'default'): bool
    {
        $webhookUrl = $this->webhookUrl($channel);
        if (!$webhookUrl) return false;
        Http::timeout(5)->retry(2, 250)->post($webhookUrl, [
            'username' => 'GAZ ARMORY',
            'content' => '<@'.$discordUserId.'>',
            'allowed_mentions' => ['users' => [(string)$discordUserId]],
            'embeds' => [[
                'title' => $title,
                'description' => $message,
                'color' => 0x3BA55D,
                'timestamp' => now()->toIso8601String(),
            ]],
        ])->throw();
        return true;
    }

    private function webhookUrl(string $channel): ?string
    {
        return config('services.discord.webhook_urls.'.$channel)
            ?: config('services.discord.webhook_url');
    }
}
