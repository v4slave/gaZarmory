<?php

namespace Tests\Unit;

use App\Actions\IssueTreasuryItem;
use App\Models\Player;
use App\Models\TreasuryItem;
use App\Models\TreasuryItemTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class IssueTreasuryItemTest extends TestCase
{
    use DatabaseTransactions;

    public function test_issuing_item_decreases_inventory_and_creates_immutable_history(): void
    {
        [$user, $player, $item] = $this->fixtures(quantity: 5, reserved: 1);

        $transaction = $this->app->make(IssueTreasuryItem::class)->execute(
            $item, 3, $player->id, null, 'Награда за участие', $user->id,
        );

        self::assertSame(2, $item->refresh()->quantity);
        self::assertSame('issue', $transaction->type);
        self::assertSame(-3, $transaction->quantity_delta);
        self::assertSame($player->id, $transaction->recipient_player_id);
        self::assertDatabaseHas('treasury_item_transactions', [
            'id' => $transaction->id,
            'type' => 'issue',
            'quantity_delta' => -3,
            'recipient_player_id' => $player->id,
        ]);
    }

    public function test_reserved_items_cannot_be_issued(): void
    {
        [$user, $player, $item] = $this->fixtures(quantity: 5, reserved: 4);
        $this->expectException(ValidationException::class);

        $this->app->make(IssueTreasuryItem::class)->execute(
            $item, 2, $player->id, null, 'Попытка выдачи', $user->id,
        );
    }

    private function fixtures(int $quantity, int $reserved): array
    {
        $suffix = str_replace('.', '', uniqid('', true));
        $user = User::query()->create([
            'discord_id' => $suffix,
            'discord_username' => 'issuer-'.$suffix,
            'discord_display_name' => 'Issuer',
        ]);
        $player = Player::query()->create([
            'nickname' => 'Recipient-'.$suffix,
            'class' => 'melee',
            'is_active' => true,
        ]);
        $item = TreasuryItem::query()->create([
            'item_name' => 'Test item '.$suffix,
            'quantity' => $quantity,
            'reserved_quantity' => $reserved,
            'unit_value' => 100,
        ]);
        return [$user, $player, $item];
    }
}
