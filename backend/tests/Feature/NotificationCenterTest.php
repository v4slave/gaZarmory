<?php

namespace Tests\Feature;

use App\Enums\PlayerClass;
use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\ActivityDefinition;
use App\Models\ArmoryNotification;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class NotificationCenterTest extends TestCase
{
    use DatabaseTransactions;

    public function test_user_can_read_only_own_notifications_and_mark_them_as_read(): void
    {
        $user = $this->linkedUser();
        $other = $this->linkedUser();
        $own = ArmoryNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'auction_outbid',
            'data' => ['title' => 'Ставку перебили', 'message' => 'Проверьте лот.', 'url' => '/auctions'],
        ]);
        ArmoryNotification::query()->create([
            'user_id' => $other->id,
            'type' => 'payout_calculated',
            'data' => ['title' => 'Выплата', 'message' => 'Начисление готово.', 'url' => '/payouts'],
        ]);

        $this->actingAs($user)->getJson('/api/notifications')
            ->assertOk()->assertJsonPath('unread_count', 1)->assertJsonCount(1, 'items');

        $this->actingAs($user)->postJson('/api/notifications/'.$own->id.'/read')->assertOk();
        self::assertNotNull($own->fresh()->read_at);
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $user = $this->linkedUser();
        $notification = ArmoryNotification::query()->create([
            'user_id' => $this->linkedUser()->id,
            'type' => 'auction_started',
            'data' => ['title' => 'Аукцион', 'message' => 'Начался новый лот.', 'url' => '/auctions'],
        ]);

        $this->actingAs($user)->postJson('/api/notifications/'.$notification->id.'/read')->assertNotFound();
        self::assertNull($notification->fresh()->read_at);
    }

    public function test_upcoming_activity_notification_is_not_duplicated(): void
    {
        $user = $this->linkedUser();
        $definition = ActivityDefinition::query()->where('is_active', true)->firstOrFail();
        $activity = Activity::query()->create([
            'activity_definition_id' => $definition->id,
            'occurred_at' => now()->addMinutes(15),
            'gold_value' => 0,
            'created_by' => $user->id,
        ]);

        $this->artisan('activities:notify-upcoming')->assertSuccessful();
        $this->artisan('activities:notify-upcoming')->assertSuccessful();

        self::assertSame(1, ArmoryNotification::query()
            ->where('user_id', $user->id)
            ->where('dedupe_key', 'activity-upcoming-'.$activity->id)
            ->count());
    }

    private function linkedUser(): User
    {
        $suffix = str_replace('.', '', uniqid('', true));
        $user = User::query()->create(['discord_id' => $suffix, 'discord_username' => 'notify-'.$suffix]);
        $user->forceFill(['role' => UserRole::Member, 'roles' => [UserRole::Member->value]])->save();
        Player::query()->create([
            'nickname' => 'Notify'.substr($suffix, -8),
            'class' => PlayerClass::Melee,
            'is_active' => true,
        ])->forceFill(['user_id' => $user->id])->save();

        return $user;
    }
}
