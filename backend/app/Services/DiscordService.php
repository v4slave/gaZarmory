<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

final class DiscordService
{
    public function send(string $title, string $message, string $color = 'gold', string $channel = 'default', array $options = []): bool
    {
        $webhookUrl = $this->webhookUrl($channel);
        if (!$webhookUrl) return false;

        $colors = ['gold' => 0xC68A2D, 'green' => 0x3BA55D, 'red' => 0xED4245];
        $payload = [
            'username' => $this->username($channel),
            'allowed_mentions' => ['parse' => []],
            'embeds' => [$this->embed($title, $message, $colors[$color] ?? $colors['gold'], $options)],
        ];
        $roleId = (string) ($options['mention_role_id'] ?? '');
        if ($roleId !== '' && ctype_digit($roleId)) {
            $payload['content'] = '<@&'.$roleId.'>';
            $payload['allowed_mentions'] = ['roles' => [$roleId]];
        }
        Http::timeout(5)->retry(2, 250)->post($webhookUrl, $payload)->throw();

        return true;
    }

    public function sendToUser(string $discordUserId, string $title, string $message, string $channel = 'default', array $options = []): bool
    {
        $webhookUrl = $this->webhookUrl($channel);
        if (!$webhookUrl) return false;
        Http::timeout(5)->retry(2, 250)->post($webhookUrl, [
            'username' => $this->username($channel),
            'content' => '<@'.$discordUserId.'>',
            'allowed_mentions' => ['users' => [(string)$discordUserId]],
            'embeds' => [$this->embed($title, $message, $channel === 'auctions' ? 0xED4245 : 0x3BA55D, $options)],
        ])->throw();
        return true;
    }

    private function webhookUrl(string $channel): ?string
    {
        return config('services.discord.webhook_urls.'.$channel)
            ?: config('services.discord.webhook_url');
    }

    private function username(string $channel): string
    {
        return match ($channel) {
            'auctions' => 'Рыжий аукционист',
            'primes' => 'Рыжий почтальон',
            'payouts' => 'Рыжий банкир',
            default => 'GAZ ARMORY',
        };
    }

    private function embed(string $title, string $message, int $color, array $options): array
    {
        $embed = [
            'title' => $title,
            'description' => $message,
            'color' => $color,
            'timestamp' => now()->toIso8601String(),
        ];

        if (filled($options['url'] ?? null)) $embed['url'] = $options['url'];
        if (filled($options['thumbnail_url'] ?? null)) $embed['thumbnail'] = ['url' => $options['thumbnail_url']];
        if (!empty($options['fields'])) $embed['fields'] = $options['fields'];
        if (filled($options['footer'] ?? null)) $embed['footer'] = ['text' => $options['footer']];

        return $embed;
    }
}
