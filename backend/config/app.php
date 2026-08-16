<?php

return [
    'name' => env('APP_NAME', 'GAZ ARMORY'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),
    'timezone' => env('APP_TIMEZONE', 'Europe/Moscow'),
    'locale' => 'ru',
    'fallback_locale' => 'en',
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => array_filter(explode(',', env('APP_PREVIOUS_KEYS', ''))),
];
