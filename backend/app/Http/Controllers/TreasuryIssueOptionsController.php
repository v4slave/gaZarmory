<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Player;
use Illuminate\Http\Request;

final class TreasuryIssueOptionsController extends Controller
{
    public function __invoke(Request $request): array
    {
        abort_unless($request->user()->canManageGuild(), 403);
        return [
            'players' => Player::query()->where('is_active', true)->orderBy('nickname')->get(['id', 'nickname', 'class']),
            'activities' => Activity::query()->with('definition:id,name,type')->latest('occurred_at')->limit(100)->get(['id', 'activity_definition_id', 'occurred_at']),
        ];
    }
}
