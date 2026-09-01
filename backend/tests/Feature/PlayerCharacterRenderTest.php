<?php

namespace Tests\Feature;

use App\Enums\PlayerClass;
use App\Enums\UserRole;
use App\Models\Player;
use App\Models\User;
use App\Services\CharacterBackgroundRemover;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class PlayerCharacterRenderTest extends TestCase
{
    use DatabaseTransactions;

    public function test_player_can_upload_replace_and_delete_own_character_render(): void
    {
        Storage::fake('public');
        [$user, $player] = $this->linkedPlayer('renderowner');

        $this->actingAs($user)
            ->postJson('/api/players/'.$player->id.'/character-render', [
                'character_render' => UploadedFile::fake()->image('character.png', 800, 1200),
            ])
            ->assertOk()
            ->assertJsonPath('id', $player->id);

        $path = $player->fresh()->character_render_path;
        Storage::disk('public')->assertExists($path);

        $this->actingAs($user)
            ->deleteJson('/api/players/'.$player->id.'/character-render')
            ->assertOk()
            ->assertJsonPath('character_render_url', null);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_member_cannot_change_another_players_character_render(): void
    {
        Storage::fake('public');
        [$user] = $this->linkedPlayer('renderintruder');
        [, $otherPlayer] = $this->linkedPlayer('rendertarget');

        $this->actingAs($user)
            ->postJson('/api/players/'.$otherPlayer->id.'/character-render', [
                'character_render' => UploadedFile::fake()->image('character.png'),
            ])
            ->assertForbidden();
    }

    public function test_player_can_generate_character_render_preview_from_screenshot(): void
    {
        [$user, $player] = $this->linkedPlayer('renderpreview');
        $remover = $this->mock(CharacterBackgroundRemover::class);
        $remover->shouldReceive('remove')->once()->andReturn("\x89PNG\r\n\x1a\npreview");

        $this->actingAs($user)
            ->postJson('/api/players/'.$player->id.'/character-render/preview', [
                'character_screenshot' => UploadedFile::fake()->image('screenshot.jpg', 1920, 1080),
            ])
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    private function linkedPlayer(string $prefix): array
    {
        $suffix = str_replace('.', '', uniqid('', true));
        $user = User::query()->create(['discord_id' => $suffix, 'discord_username' => $prefix.$suffix]);
        $user->forceFill(['role' => UserRole::Member, 'roles' => [UserRole::Member->value]])->save();
        $player = Player::query()->create(['nickname' => ucfirst($prefix).$user->id, 'class' => PlayerClass::Melee, 'is_active' => true]);
        $player->forceFill(['user_id' => $user->id])->save();

        return [$user, $player];
    }
}
