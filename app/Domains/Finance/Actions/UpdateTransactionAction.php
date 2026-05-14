<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\DTOs\TransactionDTO;
use App\Domains\Finance\Enums\TransactionType;
use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\Transaction;
use Illuminate\Support\Facades\DB;

final class UpdateTransactionAction
{
    public function execute(Transaction $transaction, TransactionDTO $dto): Transaction
    {
        return DB::transaction(function () use ($transaction, $dto) {
            $this->reverseAccountBalance($transaction);

            $transaction->update([
                'account_id' => $dto->accountId,
                'card_id' => $dto->cardId,
                'category_id' => $dto->categoryId,
                'type' => $dto->type,
                'amount' => $dto->amount,
                'description' => $dto->description,
                'notes' => $dto->notes,
                'transaction_date' => $dto->transactionDate,
                'is_recurring' => $dto->isRecurring,
                'recurrence_config' => $dto->recurrenceConfig,
            ]);

            $this->applyAccountBalance($transaction);

            return $transaction->load(['category', 'account', 'card']);
        });
    }

    private function reverseAccountBalance(Transaction $transaction): void
    {
        if (! $transaction->account_id) {
            return;
        }

        $account = BankAccount::find($transaction->account_id);
        if (! $account) {
            return;
        }

        $delta = $transaction->type === TransactionType::Income
            ? -$transaction->amount
            : $transaction->amount;

        $account->increment('balance', $delta);
    }

    private function applyAccountBalance(Transaction $transaction): void
    {
        if (! $transaction->account_id) {
            return;
        }

        $account = BankAccount::find($transaction->account_id);
        if (! $account) {
            return;
        }

        $delta = $transaction->type === TransactionType::Income
            ? $transaction->amount
            : -$transaction->amount;

        $account->increment('balance', $delta);
    }
}
