<?php

namespace App\Http\Controllers;

use App\Models\ArmoryNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminSettingsController extends Controller
{
    public function __invoke(Request $request): array
    {
        abort_unless($request->user()->canAdministrate(), 403);
        $token = DB::table('treasury_token_settings')->where('id', 1)->first();

        return [
            'economy' => [
                'token_unit_value' => (int) ($token?->token_unit_value ?? 0),
                'token_updated_at' => $token?->updated_at,
            ],
            'discord' => [
                'client_configured' => filled(config('services.discord.client_id')) && filled(config('services.discord.client_secret')),
                'webhook_configured' => filled(config('services.discord.webhook_url')),
            ],
            'notifications' => [
                'total' => ArmoryNotification::query()->count(),
                'unread' => ArmoryNotification::query()->whereNull('read_at')->count(),
                'last_created_at' => ArmoryNotification::query()->latest()->value('created_at'),
            ],
            'checked_at' => now(),
        ];
    }
}
