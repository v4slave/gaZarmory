<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class PlayerLinkGateTest extends TestCase
{
    use DatabaseTransactions;

    public function test_unlinked_user_can_read_self_and_link_options_but_cannot_read_guild_data(): void
    {
        $user = User::query()->create([
            'discord_id' => uniqid('discord-', true),
            'discord_username' => 'unlinked',
        ]);

        parent::actingAs($user);
        $this->getJson('/api/me')->assertOk();
        $this->getJson('/api/me/player-options')->assertOk();
        $this->getJson('/api/players')->assertForbidden()
            ->assertJsonPath('message', 'Сначала привяжите игрового персонажа.');
    }
}
