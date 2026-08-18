<?php

namespace Tests\Feature;

use App\Enums\PlayerClass;
use App\Enums\UserRole;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class TreasuryTokenBalanceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_treasury_returns_token_gold_equivalent(): void
    {
        DB::table('treasury_token_settings')->where('id', 1)->update([
            'token_count' => 12,
            'token_unit_value' => 150,
        ]);

        $user = User::query()->create([
            'discord_id' => 'token-test-'.uniqid(),
            'discord_username' => 'token-test',
        ]);
        $user->forceFill([
            'role' => UserRole::Member,
            'roles' => [UserRole::Member->value],
        ])->save();
        Player::query()->create([
            'nickname' => 'TokenTest',
            'class' => PlayerClass::Melee,
            'is_active' => true,
        ])->forceFill(['user_id' => $user->id])->save();

        $this->actingAs($user)->getJson('/api/treasury')
            ->assertOk()
            ->assertJsonPath('token_count', 12)
            ->assertJsonPath('token_unit_value', 150)
            ->assertJsonPath('token_gold_value', 1800);
    }
}
