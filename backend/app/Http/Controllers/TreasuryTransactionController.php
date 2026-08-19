<?php

namespace App\Http\Controllers;

use App\Actions\CreateManualTreasuryTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class TreasuryTransactionController extends Controller
{
    public function store(Request $request, CreateManualTreasuryTransaction $action): JsonResponse
    {
        abort_unless($request->user()->canHandleTreasuryItems(), 403);
        $data = $request->validate([
            'operation' => ['required', Rule::in(['income', 'expense'])],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:500'],
        ]);

        $transaction = $action->execute(
            $data['operation'],
            (int) $data['amount'],
            $data['description'],
            $request->user()->id,
        );

        return response()->json($transaction, 201);
    }
}
