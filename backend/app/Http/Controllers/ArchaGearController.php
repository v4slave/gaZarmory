<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Player;
use App\Services\ArchaGearImporter;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ArchaGearController extends Controller
{
    private const SLOTS = ['Костюм','Голова','Нагрудник','Пояс','Наручи','Перчатки','Плащ','Поножи','Обувь','Бельё','Ожерелье','Серьга 1','Серьга 2','Кольцо 1','Кольцо 2','Основное оружие','Левая рука','Лук','Музыкальный инструмент'];

    public function image(string $itemId): Response
    {
        return $this->asset('items', $itemId);
    }

    public function asset(string $type, string $assetId): Response
    {
        abort_unless(in_array($type, ['items', 'runes', 'gems'], true), 404);
        $image = Cache::remember('archa-gear-'.$type.'-'.$assetId, now()->addDay(), function () use ($type, $assetId): string {
            $request = Http::timeout(10)->withHeaders(['Referer' => 'https://archa.ge/']);
            if (app()->environment('local')) $request->withoutVerifying();
            $response = $request->get('https://archa.ge/images/'.$type.'/'.$assetId.'.jpg');
            abort_unless($response->successful(), 404);
            return $response->body();
        });
        return response($image, 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'public, max-age=86400']);
    }

    public function self(Request $request, ArchaGearImporter $importer, AuditService $audit): JsonResponse
    {
        $player = $request->user()->player;
        abort_unless($player, 404, __('domain.profile.not_linked'));
        return $this->save($request, $player, $importer, $audit);
    }

    public function snapshot(Request $request, AuditService $audit): JsonResponse
    {
        $player = $request->user()->player;
        abort_unless($player, 404, __('domain.profile.not_linked'));
        $data = $request->validate([
            'source_url' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail): void {
                $parts = parse_url((string) $value);
                if (($parts['scheme'] ?? '') !== 'https' || strtolower($parts['host'] ?? '') !== 'archa.ge') $fail('Некорректная ссылка archa.ge.');
            }],
            'items' => ['required', 'array', 'min:1', 'max:19'],
            'items.*.slot' => ['required', 'string', Rule::in(self::SLOTS)],
            'items.*.name' => ['required', 'string', 'max:180'],
            'items.*.quality' => ['nullable', 'string', 'max:100'],
            'items.*.grade' => ['nullable', 'string', Rule::in(['basic','grand','rare','arcane','heroic','unique','celestial','divine','epic','legendary','mythic','eternal'])],
            'items.*.item_id' => ['required', 'integer', 'min:1', 'max:99999999'],
            'items.*.stats' => ['sometimes', 'array', 'max:12'],
            'items.*.stats.*' => ['string', 'max:120'],
            'items.*.rune' => ['nullable', 'array'],
            'items.*.rune.text' => ['required_with:items.*.rune', 'string', 'max:120'],
            'items.*.rune.id' => ['required_with:items.*.rune', 'integer', 'min:1', 'max:99999999'],
            'items.*.rune.grade' => ['nullable', 'string', 'max:20'],
            'items.*.gems' => ['sometimes', 'array', 'max:12'],
            'items.*.gems.*.text' => ['required', 'string', 'max:120'],
            'items.*.gems.*.id' => ['required', 'integer', 'min:1', 'max:99999999'],
            'items.*.gems.*.grade' => ['nullable', 'string', 'max:20'],
            'items.*.synthesis' => ['sometimes', 'array', 'max:8'],
            'items.*.synthesis.*' => ['string', 'max:120'],
        ]);
        $items = collect($data['items'])->unique('slot')->map(fn (array $item) => [
            'slot' => $item['slot'], 'name' => $item['name'], 'quality' => $item['quality'] ?? '',
            'grade' => $item['grade'] ?? '', 'image_url' => '/api/archa-gear/items/'.$item['item_id'],
            'stats' => $item['stats'] ?? [],
            'rune' => isset($item['rune']) ? ['text' => $item['rune']['text'], 'grade' => $item['rune']['grade'] ?? '', 'image_url' => '/api/archa-gear/assets/runes/'.$item['rune']['id']] : null,
            'gems' => collect($item['gems'] ?? [])->map(fn (array $gem) => ['text' => $gem['text'], 'grade' => $gem['grade'] ?? '', 'image_url' => '/api/archa-gear/assets/gems/'.$gem['id']])->all(),
            'synthesis' => $item['synthesis'] ?? [],
        ])->values()->all();
        if ($items === []) throw ValidationException::withMessages(['items' => 'Экипировка не найдена.']);
        $old = $player->only(['archa_gear_url', 'archa_gear_items', 'archa_gear_updated_at']);
        $player->update(['archa_gear_url' => $data['source_url'], 'archa_gear_items' => $items, 'archa_gear_updated_at' => now()]);
        $audit->record('player.archa_gear_imported', $player, $old, ['archa_gear_url' => $data['source_url'], 'items_count' => count($items), 'method' => 'browser']);
        return response()->json($player->refresh()->load(['group', 'user']));
    }

    public function player(Request $request, Player $player, ArchaGearImporter $importer, AuditService $audit): JsonResponse
    {
        abort_unless($request->user()->hasRole(UserRole::Developer), 403);
        return $this->save($request, $player, $importer, $audit);
    }

    private function save(Request $request, Player $player, ArchaGearImporter $importer, AuditService $audit): JsonResponse
    {
        $data = $request->validate(['archa_gear_url' => ['required', 'string', 'max:255']]);
        $gear = $importer->import($data['archa_gear_url']);
        $old = $player->only(['archa_gear_url', 'archa_gear_items', 'archa_gear_updated_at']);
        $player->update(['archa_gear_url' => $gear['url'], 'archa_gear_items' => $gear['items'], 'archa_gear_updated_at' => now()]);
        $audit->record('player.archa_gear_imported', $player, $old, ['archa_gear_url' => $gear['url'], 'items_count' => count($gear['items'])]);
        return response()->json($player->refresh()->load(['group', 'user']));
    }
}
