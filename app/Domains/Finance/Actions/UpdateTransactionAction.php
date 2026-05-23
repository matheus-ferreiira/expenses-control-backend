<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\DTOs\TransactionDTO;
use App\Domains\Finance\Enums\TransactionType;
use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\Transaction;
use Illuminate\Support\Facades\DB;

final class UpdateTransactionAction
{
    public function execute(Transaction $transaction, TransactionDTO $dto, string $scope = 'this_only'): Transaction
    {
        return DB::transaction(function () use ($transaction, $dto, $scope) {
            if ($scope === 'this_and_future' || $scope === 'all') {
                return $this->executeBulk($transaction, $dto, $scope);
            }

            return $this->executeSingle($transaction, $dto);
        });
    }

    // ── Single transaction update (default) ──────────────────────────────────

    private function executeSingle(Transaction $transaction, TransactionDTO $dto): Transaction
    {
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

        if ($dto->tagIds !== null) {
            $transaction->tags()->sync($dto->tagIds);
        }

        $this->applyAccountBalance($transaction);

        return $transaction->load(['category', 'account', 'card', 'tags']);
    }

    // ── Bulk update (this_and_future | all) ───────────────────────────────────

    private function executeBulk(Transaction $transaction, TransactionDTO $dto, string $scope): Transaction
    {
        $query = Transaction::where('recurrence_group_id', $transaction->recurrence_group_id);

        if ($scope === 'this_and_future') {
            $query->where('transaction_date', '>=', $transaction->transaction_date->toDateString());
        }

        $affected = $query->get();

        foreach ($affected as $occurrence) {
            // Reverse the old balance contribution before updating
            $this->reverseAccountBalance($occurrence);

            $updateData = [
                'account_id' => $dto->accountId,
                'card_id' => $dto->cardId,
                'category_id' => $dto->categoryId,
                'type' => $dto->type,
                'amount' => $dto->amount,
                'description' => $dto->description,
                'notes' => $dto->notes,
                'is_recurring' => $dto->isRecurring,
                'recurrence_config' => $dto->recurrenceConfig,
            ];

            // Only override transaction_date on the occurrence the user directly selected
            if ($occurrence->id === $transaction->id) {
                $updateData['transaction_date'] = $dto->transactionDate;
            }

            $occurrence->update($updateData);

            if ($dto->tagIds !== null) {
                $occurrence->tags()->sync($dto->tagIds);
            }

            // Re-apply balance with the new amount / new account
            $this->applyAccountBalance($occurrence);
        }

        return $transaction->fresh(['category', 'account', 'card', 'tags']);
    }

    // ── Balance helpers ───────────────────────────────────────────────────────

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
