<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Player;
use App\Services\ParticipantScreenshotRecognizer;
use Illuminate\Http\Request;

final class ActivityParticipantScanController extends Controller
{
    public function __invoke(Request $request, Activity $activity, ParticipantScreenshotRecognizer $recognizer): array
    {
        $this->authorize('update', $activity);
        abort_if($activity->completed_at || $activity->earnings()->exists(), 409, __('domain.activity.participants_locked'));
        $data = $request->validate([
            'screenshot' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:12288'],
        ]);
        $existingIds = $activity->players()->pluck('players.id');
        $players = Player::query()->where('is_active', true)->whereNotIn('id', $existingIds)->get();

        return $recognizer->recognize($data['screenshot'], $players);
    }
}
