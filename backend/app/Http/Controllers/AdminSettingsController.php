<?php

namespace App\Http\Controllers;

use App\Models\ArmoryNotification;
use App\Models\TreasuryTokenSetting;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

    public function updateEconomy(Request $request, AuditService $audit): array
    {
        abort_unless($request->user()->canAdministrate(), 403);
        $data = $request->validate([
            'token_unit_value' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'updated_at' => ['nullable', 'date'],
        ]);

        return DB::transaction(function () use ($data, $audit): array {
            $setting = TreasuryTokenSetting::query()->lockForUpdate()->findOrFail(1);
            if (isset($data['updated_at']) && !$setting->updated_at?->equalTo($data['updated_at'])) {
                throw ValidationException::withMessages(['updated_at' => 'Стоимость жетона уже изменена другим пользователем. Обновите страницу.']);
            }

            $old = ['token_unit_value' => $setting->token_unit_value];
            $setting->update(['token_unit_value' => $data['token_unit_value']]);
            $audit->record('treasury_token_setting.updated', $setting, $old, ['token_unit_value' => $setting->token_unit_value]);

            return [
                'token_unit_value' => $setting->token_unit_value,
                'token_updated_at' => $setting->updated_at,
            ];
        });
    }
}
