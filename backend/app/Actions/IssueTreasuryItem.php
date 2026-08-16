<?php

namespace App\Actions;

use App\Models\Activity;
use App\Models\Player;
use App\Models\TreasuryItem;
use App\Models\TreasuryItemTransaction;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class IssueTreasuryItem
{
    public function __construct(private readonly AuditService $audit) {}

    public function execute(
        TreasuryItem $item,
        int $quantity,
        int $recipientPlayerId,
        ?int $sourceActivityId,
        string $reason,
        int $userId,
    ): TreasuryItemTransaction {
        return DB::transaction(function () use ($item, $quantity, $recipientPlayerId, $sourceActivityId, $reason, $userId): TreasuryItemTransaction {
            $lockedItem = TreasuryItem::query()->lockForUpdate()->findOrFail($item->id);
            $recipient = Player::query()->lockForUpdate()->findOrFail($recipientPlayerId);

            if (!$recipient->is_active) {
                throw ValidationException::withMessages(['recipient_player_id' => 'Нельзя выдать предмет неактивному игроку.']);
            }
            if ($quantity > $lockedItem->available_quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'Недостаточно свободного количества. Доступно: '.$lockedItem->available_quantity.'.',
                ]);
            }
            if ($sourceActivityId !== null) {
                Activity::query()->findOrFail($sourceActivityId);
            }

            $oldQuantity = $lockedItem->quantity;
            $lockedItem->decrement('quantity', $quantity);
            $transaction = TreasuryItemTransaction::query()->create([
                'treasury_item_id' => $lockedItem->id,
                'type' => 'issue',
                'quantity_delta' => -$quantity,
                'recipient_player_id' => $recipient->id,
                'source_activity_id' => $sourceActivityId,
                'reason' => $reason,
                'created_by' => $userId,
            ]);
            $this->audit->record('treasury_item.issued', $lockedItem, ['quantity' => $oldQuantity], [
                'quantity' => $lockedItem->quantity,
                'issued_quantity' => $quantity,
                'recipient_player_id' => $recipient->id,
                'source_activity_id' => $sourceActivityId,
                'reason' => $reason,
            ]);
            return $transaction->load(['item', 'recipient:id,nickname', 'sourceActivity.definition:id,name,type', 'creator:id,discord_username,discord_display_name']);
        });
    }
}
