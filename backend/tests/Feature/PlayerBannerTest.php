<?php

namespace Tests\Feature;

use App\Enums\PlayerClass;
use App\Enums\UserRole;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class PlayerBannerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_player_can_upload_and_delete_own_banner(): void
    {
        Storage::fake('public');
        [$user, $player] = $this->linkedPlayer();

        $this->actingAs($user)->postJson('/api/players/'.$player->id.'/banner', [
            'banner' => UploadedFile::fake()->image('banner.jpg', 1600, 400)->size(2048),
        ])->assertOk()->assertJsonPath('banner_url', fn ($url) => str_contains($url, '/storage/player-banners/'));

        $path = $player->fresh()->banner_path;
        Storage::disk('public')->assertExists($path);

        $this->actingAs($user)->deleteJson('/api/players/'.$player->id.'/banner')
            ->assertOk()->assertJsonPath('banner_url', null);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_player_cannot_change_someone_elses_banner(): void
    {
        Storage::fake('public');
        [$user] = $this->linkedPlayer();
        [, $otherPlayer] = $this->linkedPlayer();

        $this->actingAs($user)->postJson('/api/players/'.$otherPlayer->id.'/banner', [
            'banner' => UploadedFile::fake()->image('banner.png'),
        ])->assertForbidden();
    }

    public function test_banner_rejects_unsupported_files(): void
    {
        Storage::fake('public');
        [$user, $player] = $this->linkedPlayer();

        $this->actingAs($user)->postJson('/api/players/'.$player->id.'/banner', [
            'banner' => UploadedFile::fake()->create('banner.svg', 20, 'image/svg+xml'),
        ])->assertUnprocessable()->assertJsonValidationErrors('banner');
    }

    private function linkedPlayer(): array
    {
        $suffix = str_replace('.', '', uniqid('', true));
        $user = User::query()->create(['discord_id' => $suffix, 'discord_username' => 'banner-'.$suffix]);
        $user->forceFill(['role' => UserRole::Member, 'roles' => [UserRole::Member->value]])->save();
        $player = Player::query()->create(['nickname' => 'Banner'.$suffix, 'class' => PlayerClass::Melee, 'is_active' => true]);
        $player->forceFill(['user_id' => $user->id])->save();

        return [$user, $player];
    }
}
