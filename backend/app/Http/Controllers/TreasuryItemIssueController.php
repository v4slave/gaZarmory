<?php

namespace App\Http\Controllers;

use App\Actions\IssueTreasuryItem;
use App\Models\TreasuryItem;
use App\Models\TreasuryItemTransaction;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class TreasuryItemIssueController extends Controller
{
    public function __invoke(Request $request, TreasuryItem $item, IssueTreasuryItem $action): TreasuryItemTransaction
    {
        abort_unless($request->user()->canHandleTreasuryItems(), 403);
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'recipient_player_id' => [
                'required', 'integer',
                Rule::exists('players', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'source_activity_id' => ['nullable', 'integer', 'exists:activities,id'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        return $action->execute(
            $item,
            $data['quantity'],
            $data['recipient_player_id'],
            $data['source_activity_id'] ?? null,
            $data['reason'],
            $request->user()->id,
        );
    }
}
