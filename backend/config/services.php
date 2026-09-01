<?php

return [
    'archa' => [
        'node_binary' => env('ARCHA_IMPORT_NODE_BINARY', 'node'),
    ],
    'discord' => [
        'client_id' => env('DISCORD_CLIENT_ID'),
        'client_secret' => env('DISCORD_CLIENT_SECRET'),
        'redirect' => env('DISCORD_REDIRECT_URI'),
        'webhook_url' => env('DISCORD_WEBHOOK_URL'),
        'member_role_id' => env('DISCORD_MEMBER_ROLE_ID'),
        'webhook_urls' => [
            'auctions' => env('DISCORD_AUCTIONS_WEBHOOK_URL'),
            'primes' => env('DISCORD_PRIMES_WEBHOOK_URL'),
            'payouts' => env('DISCORD_PAYOUTS_WEBHOOK_URL'),
        ],
    ],
];
