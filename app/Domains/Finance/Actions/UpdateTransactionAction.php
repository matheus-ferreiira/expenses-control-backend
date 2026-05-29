<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\DTOs\TransactionDTO;
use App\Domains\Finance\Enums\TransactionStatus;
use App\Domains\Finance\Enums\TransactionType;
use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class UpdateTransactionAction
{
    /** Number of months to generate when promoting a transaction to a fix series (5 years). */
    private const RECURRENCE_MONTHS = 60;

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

        // Detect promotion to fix series: was non-recurring, now marked as recurring
        $becomingRecurring = $dto->isRecurring && ! $transaction->is_recurring;
        $recurrenceGroupId = $becomingRecurring
            ? (string) Str::uuid()
            : $transaction->recurrence_group_id;

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
            'recurrence_group_id' => $recurrenceGroupId,
        ]);

        if ($dto->tagIds !== null) {
            $transaction->tags()->sync($dto->tagIds);
        }

        $this->applyAccountBalance($transaction);

        // If transaction was just promoted to a fix series, generate the 59 future pending months
        if ($becomingRecurring) {
            $this->createFutureOccurrences($transaction, $dto, $recurrenceGroupId);
        }

        return $transaction->load(['category', 'account', 'card', 'tags']);
    }

    /**
     * Generates months 1..59 as pending occurrences for a transaction just promoted to a fix series.
     * Month 0 (confirmed) is the existing transaction that was just updated.
     */
    private function createFutureOccurrences(Transaction $transaction, TransactionDTO $dto, string $groupId): void
    {
        $baseDate = Carbon::parse($dto->transactionDate);

        for ($i = 1; $i < self::RECURRENCE_MONTHS; $i++) {
            Transaction::create([
                'user_id' => $transaction->user_id,
                'account_id' => $dto->accountId,
                'card_id' => $dto->cardId,
                'category_id' => $dto->categoryId,
                'type' => $dto->type,
                'amount' => $dto->amount,
                'description' => $dto->description,
                'notes' => $dto->notes,
                'transaction_date' => $baseDate->copy()->addMonths($i)->toDateString(),
                'is_recurring' => true,
                'recurrence_config' => $dto->recurrenceConfig,
                'recurrence_group_id' => $groupId,
                'status' => TransactionStatus::Pending->value,
            ]);
        }
    }

    // ── Bulk update (this_and_future | all) ───────────────────────────────────

    private function executeBulk(Transaction $transaction, TransactionDTO $dto, string $scope): Transaction
    {
        // Installment group takes priority if this transaction belongs to one
        if ($transaction->installment_group_id) {
            $query = Transaction::where('installment_group_id', $transaction->installment_group_id);

            if ($scope === 'this_and_future') {
                $query->where('installment_number', '>=', $transaction->installment_number);
            }
        } else {
            $query = Transaction::where('recurrence_group_id', $transaction->recurrence_group_id);

            if ($scope === 'this_and_future') {
                $query->where('transaction_date', '>=', $transaction->transaction_date->toDateString());
            }
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
        // Pending transactions never affected the balance — nothing to reverse
        if (! $transaction->isConfirmed() || ! $transaction->account_id) {
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
        // Only confirmed transactions affect the balance
        if (! $transaction->isConfirmed() || ! $transaction->account_id) {
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
