<?php

namespace Tests\Feature;

use App\Enums\PlayerClass;
use App\Enums\UserRole;
use App\Jobs\SendDiscordNotification;
use App\Models\Activity;
use App\Models\ActivityDefinition;
use App\Models\ArmoryNotification;
use App\Models\Player;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
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

    public function test_expired_notifications_are_hidden_and_pruned(): void
    {
        config(['notifications.retention_days' => 7]);
        $user = $this->linkedUser();
        $expired = ArmoryNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'auction_started',
            'data' => ['title' => 'Старый аукцион', 'message' => 'Лот уже не актуален.', 'url' => '/auctions'],
        ]);
        $expired->forceFill(['created_at' => now()->subDays(8), 'updated_at' => now()->subDays(8)])->save();
        $current = ArmoryNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'auction_started',
            'data' => ['title' => 'Новый аукцион', 'message' => 'Лот открыт.', 'url' => '/auctions'],
        ]);

        $this->actingAs($user)->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $current->id);

        $this->artisan('notifications:prune')->assertSuccessful();

        self::assertDatabaseMissing('notifications', ['id' => $expired->id]);
        self::assertDatabaseHas('notifications', ['id' => $current->id]);
    }

    public function test_upcoming_activity_notification_is_not_duplicated(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-30 12:00:00', 'Europe/Moscow'));

        try {
            config(['services.discord.member_role_id' => '123456789012345678']);
            Queue::fake([SendDiscordNotification::class]);
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
            Queue::assertPushed(SendDiscordNotification::class, 1);
            Queue::assertPushed(fn (SendDiscordNotification $job) =>
                $job->title === 'Прайм · '.$definition->name
                && $job->options['mention_role_id'] === '123456789012345678'
            );
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_scheduled_prime_is_announced_without_created_activity(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-30 10:59:00', 'Europe/Moscow'));

        try {
            config(['services.discord.member_role_id' => '123456789012345678']);
            Queue::fake([SendDiscordNotification::class]);
            $user = $this->linkedUser();

            $this->artisan('activities:notify-upcoming')->assertSuccessful();
            Queue::assertNothingPushed();

            CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-30 11:00:00', 'Europe/Moscow'));
            $this->artisan('activities:notify-upcoming')->assertSuccessful();
            $this->artisan('activities:notify-upcoming')->assertSuccessful();

            self::assertSame(1, ArmoryNotification::query()
                ->where('user_id', $user->id)
                ->where('type', 'activity_upcoming')
                ->where('data->message', 'АГЛ начнётся 30.08.2026 11:20.')
                ->count());
            Queue::assertPushed(SendDiscordNotification::class, 1);
            Queue::assertPushed(fn (SendDiscordNotification $job) =>
                $job->title === 'Прайм · АГЛ'
                && $job->options['mention_role_id'] === '123456789012345678'
                && str_contains($job->options['fields'][2]['value'], "\n")
                && !str_contains($job->options['fields'][2]['value'], '\\n')
            );
        } finally {
            CarbonImmutable::setTestNow();
        }
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
