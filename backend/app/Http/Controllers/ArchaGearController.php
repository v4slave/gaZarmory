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

final class ArchaGearController extends Controller
{
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
