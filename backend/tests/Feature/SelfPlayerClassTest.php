<?php

namespace Tests\Feature;

use App\Enums\PlayerClass;
use App\Enums\UserRole;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class SelfPlayerClassTest extends TestCase
{
    use DatabaseTransactions;

    public function test_user_can_change_class_of_linked_player(): void
    {
        $suffix = str_replace('.', '', uniqid('', true));
        $user = User::query()->create([
            'discord_id' => $suffix,
            'discord_username' => 'class-'.$suffix,
        ]);
        $user->forceFill([
            'roles' => [UserRole::Member->value],
            'role' => UserRole::Member,
        ])->save();

        $player = Player::query()->create([
            'nickname' => 'Class-'.$suffix,
            'class' => PlayerClass::Melee,
            'is_active' => true,
        ]);
        $player->forceFill(['user_id' => $user->id])->save();

        $this->actingAs($user)
            ->patchJson('/api/me/player/class', ['class' => PlayerClass::Healer->value])
            ->assertOk()
            ->assertJsonPath('class', PlayerClass::Healer->value);

        $this->assertDatabaseHas('players', [
            'id' => $player->id,
            'class' => PlayerClass::Healer->value,
        ]);
    }

    public function test_class_must_be_supported(): void
    {
        $suffix = str_replace('.', '', uniqid('', true));
        $user = User::query()->create([
            'discord_id' => $suffix,
            'discord_username' => 'class-validation-'.$suffix,
        ]);
        $user->forceFill([
            'roles' => [UserRole::Member->value],
            'role' => UserRole::Member,
        ])->save();

        $player = Player::query()->create([
            'nickname' => 'ClassValidation-'.$suffix,
            'class' => PlayerClass::Melee,
            'is_active' => true,
        ]);
        $player->forceFill(['user_id' => $user->id])->save();

        $this->actingAs($user)
            ->patchJson('/api/me/player/class', ['class' => 'unknown'])
            ->assertUnprocessable();
    }

    public function test_user_can_update_gear_score_and_assets(): void
    {
        $suffix = str_replace('.', '', uniqid('', true));
        $user = User::query()->create(['discord_id' => $suffix, 'discord_username' => 'profile-'.$suffix]);
        $user->forceFill(['roles' => [UserRole::Member->value], 'role' => UserRole::Member])->save();
        $player = Player::query()->create(['nickname' => 'Profile'.$suffix, 'class' => PlayerClass::Melee, 'is_active' => true]);
        $player->forceFill(['user_id' => $user->id])->save();
        $payload = [
            'gear_score' => 14321, 'has_ship' => true, 'has_tank' => false,
            'has_fuchsias' => true, 'has_clouds' => false, 'has_machaon' => true,
            'has_tare' => false, 'has_deer' => true, 'has_invulnerable_pet' => true,
            'has_shield_swap' => true, 'has_flippers' => false,
        ];

        $this->actingAs($user)->patchJson('/api/me/player/profile', $payload)
            ->assertOk()->assertJsonPath('gear_score', 14321)->assertJsonPath('has_ship', true);

        $this->assertDatabaseHas('players', ['id' => $player->id, 'gear_score' => 14321, 'has_ship' => true]);
    }
}
