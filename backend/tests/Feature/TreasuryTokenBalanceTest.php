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

    public function test_treasury_calculates_whole_tokens_from_gold_and_unit_value(): void
    {
        DB::table('treasury_token_settings')->where('id', 1)->update([
            'token_unit_value' => 80,
        ]);
        DB::table('treasury_transactions')->insert([
            'type' => 'adjustment',
            'amount' => 1000,
            'balance_after' => 1000,
            'description' => 'Баланс для проверки жетонов',
            'created_at' => now(),
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
            ->assertJsonPath('token_unit_value', 80)
            ->assertJsonMissingPath('token_gold_value');
    }
}
