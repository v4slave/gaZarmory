<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\LootCatalogItem;
use App\Models\TreasuryItem;
use App\Models\TreasuryItemTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class DeveloperTreasuryItemAddTest extends TestCase
{
    use DatabaseTransactions;

    public function test_developer_can_add_catalog_item_without_activity(): void
    {
        $developer = $this->user(UserRole::Developer);
        $catalogItem = LootCatalogItem::query()->create([
            'name' => 'Direct treasury item '.uniqid(),
            'rarity' => 'epic',
            'icon_path' => 'loot-catalog/direct.png',
            'is_active' => true,
        ]);

        $response = $this->actingAs($developer)->postJson('/api/treasury/items', [
            'loot_catalog_item_id' => $catalogItem->id,
            'quantity' => 3,
            'unit_value' => 125,
            'reason' => 'Добавлено разработчиком',
        ])->assertCreated()
            ->assertJsonPath('type', 'adjustment')
            ->assertJsonPath('quantity_delta', 3)
            ->assertJsonPath('source_activity_id', null);

        $item = TreasuryItem::query()->where('item_name', $catalogItem->name)->firstOrFail();
        self::assertSame(3, $item->quantity);
        self::assertSame(125, $item->unit_value);
        self::assertSame('epic', $item->rarity);
        self::assertSame($item->id, $response->json('treasury_item_id'));
    }

    public function test_repeated_addition_increases_existing_quantity(): void
    {
        $developer = $this->user(UserRole::Developer);
        $catalogItem = LootCatalogItem::query()->create([
            'name' => 'Existing treasury item '.uniqid(),
            'rarity' => 'rare',
            'is_active' => true,
        ]);
        $item = TreasuryItem::query()->create([
            'item_name' => $catalogItem->name,
            'quantity' => 4,
            'reserved_quantity' => 1,
            'unit_value' => 10,
            'rarity' => 'rare',
        ]);

        $this->actingAs($developer)->postJson('/api/treasury/items', [
            'loot_catalog_item_id' => $catalogItem->id,
            'quantity' => 2,
            'unit_value' => 20,
            'reason' => 'Пополнение остатка',
        ])->assertCreated();

        self::assertSame(6, $item->refresh()->quantity);
        self::assertSame(1, $item->reserved_quantity);
        self::assertSame(20, $item->unit_value);
        self::assertDatabaseHas('treasury_item_transactions', [
            'treasury_item_id' => $item->id,
            'quantity_delta' => 2,
            'source_activity_id' => null,
        ]);
    }

    public function test_non_developer_cannot_add_item_directly(): void
    {
        $leader = $this->user(UserRole::GuildLeader);
        $catalogItem = LootCatalogItem::query()->create([
            'name' => 'Restricted direct item '.uniqid(),
            'rarity' => 'common',
            'is_active' => true,
        ]);

        $this->actingAs($leader)->postJson('/api/treasury/items', [
            'loot_catalog_item_id' => $catalogItem->id,
            'quantity' => 1,
            'unit_value' => 0,
            'reason' => 'Недоступная операция',
        ])->assertForbidden();
    }

    private function user(UserRole $role): User
    {
        $suffix = str_replace('.', '', uniqid('', true));
        $user = User::query()->create([
            'discord_id' => $suffix,
            'discord_username' => 'direct-treasury-'.$suffix,
        ]);
        $user->forceFill(['role' => $role, 'roles' => [$role->value]])->save();

        return $user;
    }
}
