<?php

return [
    'archa' => [
        'node_binary' => env('ARCHA_IMPORT_NODE_BINARY', 'node'),
    ],
    'rembg' => [
        'python_binary' => env('REMBG_PYTHON_BINARY', '/var/www/gaz-armory/shared/rembg/venv/bin/python'),
        'model_dir' => env('REMBG_MODEL_DIR', '/var/www/gaz-armory/shared/rembg/models'),
        'model' => env('REMBG_MODEL', 'isnet-general-use'),
        'timeout' => (int) env('REMBG_TIMEOUT', 90),
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
