<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\PlayerClass;
use App\Models\Auction;
use App\Models\Player;
use App\Models\TreasuryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class AuctionVisibilityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_member_sees_only_active_auctions(): void
    {
        $manager = $this->user(UserRole::GuildLeader);
        $member = $this->user(UserRole::Member);
        $item = TreasuryItem::query()->create([
            'item_name' => 'Visibility item '.uniqid(),
            'quantity' => 10,
            'reserved_quantity' => 0,
            'unit_value' => 100,
        ]);
        $active = $this->auction($item, $manager, 'active');
        $draft = $this->auction($item, $manager, 'draft');
        $cancelled = $this->auction($item, $manager, 'cancelled');

        $response = $this->actingAs($member)->getJson('/api/auctions')->assertOk();
        $ids = collect($response->json())->pluck('id');

        self::assertTrue($ids->contains($active->id));
        self::assertFalse($ids->contains($draft->id));
        self::assertFalse($ids->contains($cancelled->id));
        $this->actingAs($member)->getJson('/api/auctions/'.$draft->id)->assertNotFound();
    }

    public function test_manager_sees_all_auction_statuses_and_active_count(): void
    {
        $manager = $this->user(UserRole::Developer);
        $item = TreasuryItem::query()->create([
            'item_name' => 'Manager visibility '.uniqid(),
            'quantity' => 10,
            'reserved_quantity' => 0,
            'unit_value' => 100,
        ]);
        $active = $this->auction($item, $manager, 'active');
        $draft = $this->auction($item, $manager, 'draft');

        $ids = collect($this->actingAs($manager)->getJson('/api/auctions')->assertOk()->json())->pluck('id');
        self::assertTrue($ids->contains($active->id));
        self::assertTrue($ids->contains($draft->id));
        $this->actingAs($manager)->getJson('/api/auctions/active-count')
            ->assertOk()
            ->assertJsonStructure(['count']);
    }

    public function test_auction_must_have_at_least_ten_minutes_remaining(): void
    {
        $manager = $this->user(UserRole::GuildLeader);
        $item = TreasuryItem::query()->create([
            'item_name' => 'Timed auction '.uniqid(),
            'quantity' => 2,
            'reserved_quantity' => 0,
            'unit_value' => 100,
        ]);
        $payload = [
            'treasury_item_id' => $item->id,
            'quantity' => 1,
            'starting_bid' => 100,
            'minimum_step' => 10,
        ];

        $this->actingAs($manager)->postJson('/api/auctions', $payload + ['ends_at' => now()->addMinutes(9)->toISOString()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ends_at');
        $this->actingAs($manager)->postJson('/api/auctions', $payload + ['ends_at' => now()->addMinutes(11)->toISOString()])
            ->assertCreated();
    }

    private function auction(TreasuryItem $item, User $creator, string $status): Auction
    {
        return Auction::query()->create([
            'treasury_item_id' => $item->id,
            'quantity' => 1,
            'starting_bid' => 100,
            'minimum_step' => 10,
            'ends_at' => now()->addDay(),
            'status' => $status,
            'created_by' => $creator->id,
        ]);
    }

    private function user(UserRole $role): User
    {
        $suffix = str_replace('.', '', uniqid('', true));
        $user = User::query()->create(['discord_id' => $suffix, 'discord_username' => 'auction-'.$suffix]);
        $user->forceFill(['role' => $role, 'roles' => [$role->value]])->save();
        Player::query()->create(['nickname' => 'Auction'.$user->id, 'class' => PlayerClass::Melee, 'is_active' => false])
            ->forceFill(['user_id' => $user->id])->save();

        return $user;
    }
}
