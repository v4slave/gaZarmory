<?php

namespace Tests\Feature;

use App\Enums\PlayerClass;
use App\Enums\UserRole;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ArchaGearImportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_linked_player_can_import_archa_gear_slots(): void
    {
        Http::fake(['https://archa.ge/*' => Http::response($this->fixture(), 200)]);
        $suffix = str_replace('.', '', uniqid('', true));
        $user = User::query()->create(['discord_id' => $suffix, 'discord_username' => 'archa-'.$suffix]);
        $user->forceFill(['roles' => [UserRole::Member->value], 'role' => UserRole::Member])->save();
        $player = Player::query()->create(['nickname' => 'Archa'.$suffix, 'class' => PlayerClass::Mage, 'is_active' => true]);
        $player->forceFill(['user_id' => $user->id])->save();

        $this->actingAs($user)->postJson('/api/me/player/archa-gear', [
            'archa_gear_url' => 'https://archa.ge/?bid=61&u=216950152060076033',
        ])->assertOk()
            ->assertJsonPath('archa_gear_url', 'https://archa.ge/?u=216950152060076033&bid=61')
            ->assertJsonPath('archa_gear_items.0.slot', 'Голова')
            ->assertJsonPath('archa_gear_items.0.name', '+15 Проклятый рамианский кожаный капюшон')
            ->assertJsonPath('archa_gear_items.0.grade', 'epic')
            ->assertJsonPath('archa_gear_items.0.image_url', '/api/archa-gear/items/45851');

        $this->assertCount(1, $player->refresh()->archa_gear_items);
    }

    public function test_import_rejects_non_archa_urls_without_requesting_them(): void
    {
        Http::fake();
        $suffix = str_replace('.', '', uniqid('', true));
        $user = User::query()->create(['discord_id' => $suffix, 'discord_username' => 'safe-'.$suffix]);
        $user->forceFill(['roles' => [UserRole::Member->value], 'role' => UserRole::Member])->save();
        $player = Player::query()->create(['nickname' => 'Safe'.$suffix, 'class' => PlayerClass::Mage, 'is_active' => true]);
        $player->forceFill(['user_id' => $user->id])->save();

        $this->actingAs($user)->postJson('/api/me/player/archa-gear', [
            'archa_gear_url' => 'https://example.com/?u=1&bid=2',
        ])->assertUnprocessable()->assertJsonValidationErrors('archa_gear_url');
        Http::assertNothingSent();
    }

    private function fixture(): string
    {
        $popover = htmlspecialchars("<div class='popover-body'><div class='row'><div class='col-9 aa-grade-9'>Предмет Эпохи Сказаний<br>+15 Проклятый рамианский кожаный капюшон</div></div></div>", ENT_QUOTES | ENT_HTML5);
        return '<html><body><div class="aa-itemslot"><img src="images/slots/slot0.png"></div><div class="aa-itemslot aa-slot-grade-9"><img src="images/items/45851.jpg"><img class="aa-gradecorner" src="images/grades/item_grade_Epic.png" data-bs-content="'.$popover.'"></div></body></html>';
    }
}
