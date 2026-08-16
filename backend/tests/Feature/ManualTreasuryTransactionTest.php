<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\TreasuryTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

final class ManualTreasuryTransactionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_developer_can_add_and_spend_gold(): void
    {
        $developer = $this->userWithRole(UserRole::Developer);
        $initial = (int) (TreasuryTransaction::query()->latest('id')->value('balance_after') ?? 0);

        $this->actingAs($developer)->postJson('/api/treasury/transactions', [
            'operation' => 'income', 'amount' => 500, 'description' => 'Взнос',
        ])->assertCreated()->assertJsonPath('balance_after', $initial + 500);

        $this->actingAs($developer)->postJson('/api/treasury/transactions', [
            'operation' => 'expense', 'amount' => 200, 'description' => 'Расходники',
        ])->assertCreated()->assertJsonPath('balance_after', $initial + 300);
    }

    public function test_expense_cannot_make_treasury_negative(): void
    {
        $leader = $this->userWithRole(UserRole::GuildLeader);
        $balance = (int) (TreasuryTransaction::query()->latest('id')->value('balance_after') ?? 0);

        $this->actingAs($leader)->postJson('/api/treasury/transactions', [
            'operation' => 'expense', 'amount' => $balance + 1, 'description' => 'Слишком большой расход',
        ])->assertUnprocessable()->assertJsonValidationErrors('amount');
    }

    public function test_member_cannot_change_treasury_balance(): void
    {
        $member = $this->userWithRole(UserRole::Member);
        $this->actingAs($member)->postJson('/api/treasury/transactions', [
            'operation' => 'income', 'amount' => 100, 'description' => 'Попытка',
        ])->assertForbidden();
    }

    private function userWithRole(UserRole $role): User
    {
        $suffix = str_replace('.', '', uniqid('', true));
        $user = User::query()->create(['discord_id' => $suffix, 'discord_username' => 'treasury-'.$suffix]);
        $user->forceFill(['role' => $role, 'roles' => [$role->value]])->save();
        return $user;
    }
}
