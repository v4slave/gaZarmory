<?php

namespace Tests\Feature;

use App\Enums\PlayerClass;
use App\Enums\UserRole;
use App\Models\Player;
use App\Models\PlayerLinkRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class PlayerLinkRequestTest extends TestCase
{
    use DatabaseTransactions;

    public function test_unlinked_user_creates_request_instead_of_claiming_player(): void
    {
        $user = $this->user(UserRole::Member, false);
        $player = Player::query()->create(['nickname' => 'Заявитель'.substr($user->discord_id, -5), 'class' => PlayerClass::Melee, 'is_active' => true]);

        $this->actingAs($user)->postJson('/api/me/player', ['player_id' => $player->id])
            ->assertCreated()->assertJsonPath('status', 'pending');

        self::assertNull($player->fresh()->user_id);
        $this->assertDatabaseHas('player_link_requests', ['user_id' => $user->id, 'player_id' => $player->id, 'status' => 'pending']);
    }

    public function test_administrator_approves_request_and_links_player(): void
    {
        $leader = $this->user(UserRole::GuildLeader, true);
        $member = $this->user(UserRole::Member, false);
        $player = Player::query()->create(['nickname' => 'Одобряемый'.substr($member->discord_id, -5), 'class' => PlayerClass::Healer, 'is_active' => true]);
        $linkRequest = PlayerLinkRequest::query()->create(['user_id' => $member->id, 'player_id' => $player->id, 'status' => 'pending']);

        $this->actingAs($leader)->postJson('/api/admin/player-link-requests/'.$linkRequest->id.'/approve')
            ->assertOk()->assertJsonPath('status', 'approved');

        self::assertSame($member->id, $player->fresh()->user_id);
        self::assertSame($leader->id, $linkRequest->fresh()->reviewed_by);
    }

    public function test_member_cannot_review_link_request(): void
    {
        $member = $this->user(UserRole::Member, true);
        $applicant = $this->user(UserRole::Member, false);
        $player = Player::query()->create(['nickname' => 'Закрытый'.substr($applicant->discord_id, -5), 'class' => PlayerClass::Mage, 'is_active' => true]);
        $linkRequest = PlayerLinkRequest::query()->create(['user_id' => $applicant->id, 'player_id' => $player->id, 'status' => 'pending']);

        $this->actingAs($member)->postJson('/api/admin/player-link-requests/'.$linkRequest->id.'/approve')->assertForbidden();
        self::assertNull($player->fresh()->user_id);
    }

    private function user(UserRole $role, bool $linked): User
    {
        $suffix = str_replace('.', '', uniqid('', true));
        $user = User::query()->create(['discord_id' => $suffix, 'discord_username' => 'link-'.$suffix]);
        $user->forceFill(['role' => $role, 'roles' => [$role->value]])->save();
        if ($linked) {
            Player::query()->create(['nickname' => 'Linked'.substr($suffix, -8), 'class' => PlayerClass::Melee, 'is_active' => true])
                ->forceFill(['user_id' => $user->id])->save();
        }
        return $user;
    }
}
