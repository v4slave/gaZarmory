<?php

namespace Tests\Feature;

use App\Enums\PlayerClass;
use App\Enums\UserRole;
use App\Models\Player;
use App\Models\TreasuryItem;
use App\Models\TreasuryItemTransaction;
use App\Models\TreasuryTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class TreasuryPaginationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_item_and_gold_histories_are_paginated_independently(): void
    {
        $user = $this->member();
        $item = TreasuryItem::query()->create([
            'item_name' => 'Pagination '.uniqid(),
            'quantity' => 12,
            'reserved_quantity' => 0,
            'unit_value' => 100,
        ]);

        for ($index = 1; $index <= 12; $index++) {
            TreasuryItemTransaction::query()->create([
                'treasury_item_id' => $item->id,
                'type' => 'adjustment',
                'quantity_delta' => 1,
                'reason' => 'Pagination item '.$index,
                'created_by' => $user->id,
            ]);
            TreasuryTransaction::query()->create([
                'type' => 'adjustment',
                'amount' => 1,
                'balance_after' => $index,
                'description' => 'Pagination gold '.$index,
                'created_by' => $user->id,
            ]);
        }

        $response = $this->actingAs($user)->getJson('/api/treasury?item_page=2&gold_page=1')
            ->assertOk()
            ->assertJsonPath('transactions_meta.current_page', 2)
            ->assertJsonPath('transactions_meta.per_page', 10)
            ->assertJsonPath('transactions_meta.total', 12)
            ->assertJsonPath('gold_transactions_meta.current_page', 1)
            ->assertJsonPath('gold_transactions_meta.per_page', 10)
            ->assertJsonPath('gold_transactions_meta.total', 12)
            ->assertJsonCount(2, 'transactions')
            ->assertJsonCount(10, 'gold_transactions');

        self::assertSame('Pagination item 2', $response->json('transactions.0.reason'));
        self::assertSame('Pagination gold 12', $response->json('gold_transactions.0.description'));
    }

    private function member(): User
    {
        $suffix = substr(str_replace('.', '', uniqid('', true)), -10);
        $user = User::query()->create([
            'discord_id' => 'treasury-pagination-'.$suffix,
            'discord_username' => 'treasury-pagination',
        ]);
        $user->forceFill([
            'role' => UserRole::Member,
            'roles' => [UserRole::Member->value],
        ])->save();
        Player::query()->create([
            'nickname' => 'Page'.$suffix,
            'class' => PlayerClass::Melee,
            'is_active' => true,
        ])->forceFill(['user_id' => $user->id])->save();

        return $user;
    }
}
