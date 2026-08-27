<?php

namespace App\Actions;

use App\Models\TreasuryTransaction;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateManualTreasuryTransaction
{
    public function __construct(private readonly AuditService $audit) {}

    public function execute(string $operation, int $amount, string $description, int $userId): TreasuryTransaction
    {
        return DB::transaction(function () use ($operation, $amount, $description, $userId): TreasuryTransaction {
            DB::select('SELECT pg_advisory_xact_lock(?)', [834721]);
            $balance = (int) (TreasuryTransaction::query()->latest('id')->value('balance_after') ?? 0);
            $signedAmount = $operation === 'expense' ? -$amount : $amount;
            $newBalance = $balance + $signedAmount;

            if ($newBalance < 0) {
                throw ValidationException::withMessages([
                    'amount' => __('domain.treasury.insufficient_gold', ['available' => $balance]),
                ]);
            }

            $transaction = TreasuryTransaction::query()->create([
                'type' => $operation === 'expense' ? 'manual_expense' : 'manual_income',
                'amount' => $signedAmount,
                'balance_after' => $newBalance,
                'description' => $description,
                'created_by' => $userId,
            ]);

            $this->audit->record('treasury.transaction_created', $transaction, null, [
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'balance_after' => $transaction->balance_after,
                'description' => $transaction->description,
            ]);

            return $transaction->load('creator:id,discord_username,discord_display_name');
        });
    }
}
