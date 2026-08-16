<?php

namespace Tests\Feature;

use App\Enums\PlayerClass;
use App\Enums\UserRole;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class PlayerNicknameValidationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_player_creation_rejects_spaces_digits_special_characters_and_long_names(): void
    {
        $leader = $this->user(UserRole::GuildLeader);

        foreach (['Два слова', 'Player123', 'Player_Name', 'ОченьДлинныйНикнейм'] as $nickname) {
            $this->actingAs($leader)->postJson('/api/players', [
                'nickname' => $nickname,
                'class' => PlayerClass::Melee->value,
            ])->assertUnprocessable()->assertJsonValidationErrors('nickname');
        }
    }

    public function test_player_creation_accepts_cyrillic_and_latin_letters_up_to_eighteen_characters(): void
    {
        $leader = $this->user(UserRole::GuildLeader);

        $this->actingAs($leader)->postJson('/api/players', [
            'nickname' => 'ТаquelaПерсонаж',
            'class' => PlayerClass::Melee->value,
        ])->assertCreated()->assertJsonPath('nickname', 'ТаquelaПерсонаж');
    }

    public function test_self_rename_uses_the_same_nickname_rule(): void
    {
        $member = $this->user(UserRole::Member);
        $player = Player::query()->create([
            'nickname' => 'ValidName',
            'class' => PlayerClass::Melee,
            'is_active' => true,
        ]);
        $player->forceFill(['user_id' => $member->id])->save();
        $member->setRelation('player', $player);

        $this->actingAs($member)->patchJson('/api/me/player/nickname', ['nickname' => 'Bad Name'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('nickname');
        self::assertSame('ValidName', $player->fresh()->nickname);
    }

    private function user(UserRole $role): User
    {
        $suffix = str_replace('.', '', uniqid('', true));
        $user = User::query()->create(['discord_id' => $suffix, 'discord_username' => 'nickname-'.$suffix]);
        $user->forceFill(['role' => $role, 'roles' => [$role->value]])->save();

        return $user;
    }
}
