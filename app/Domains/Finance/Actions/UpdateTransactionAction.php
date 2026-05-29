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
    /** Max occurrences when promoting a transaction to a fix series, keyed by frequency. */
    private const MAX_BY_FREQUENCY = [
        'weekly' => 260,
        'biweekly' => 130,
        'monthly' => 60,
        'bimonthly' => 30,
        'quarterly' => 20,
        'semiannual' => 10,
        'annual' => 5,
    ];

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
     * Generates future occurrences (1..N-1) when a transaction is promoted to a fix series.
     * Occurrence 0 (the existing transaction) is already updated by executeSingle().
     * Reads recurrence_config for frequency and end condition; defaults to monthly/never.
     */
    private function createFutureOccurrences(Transaction $transaction, TransactionDTO $dto, string $groupId): void
    {
        $baseDate = Carbon::parse($dto->transactionDate);
        $config = $dto->recurrenceConfig ?? [];
        $frequency = $config['frequency'] ?? 'monthly';
        $endType = $config['end_type'] ?? 'never';
        $maxCount = self::MAX_BY_FREQUENCY[$frequency] ?? 60;

        $total = match ($endType) {
            'count' => min((int) ($config['count'] ?? $maxCount), $maxCount),
            'date' => $this->countUntilDate($baseDate, $frequency, $config['end_date'] ?? null, $maxCount),
            default => $maxCount,
        };

        // Start at i=1 — i=0 is the promoted transaction already saved
        for ($i = 1; $i < $total; $i++) {
            Transaction::create([
                'user_id' => $transaction->user_id,
                'account_id' => $dto->accountId,
                'card_id' => $dto->cardId,
                'category_id' => $dto->categoryId,
                'type' => $dto->type,
                'amount' => $dto->amount,
                'description' => $dto->description,
                'notes' => $dto->notes,
                'transaction_date' => $this->addInterval($baseDate->copy(), $frequency, $i)->toDateString(),
                'is_recurring' => true,
                'recurrence_config' => $dto->recurrenceConfig,
                'recurrence_group_id' => $groupId,
                'status' => TransactionStatus::Pending->value,
            ]);
        }
    }

    private function addInterval(Carbon $date, string $frequency, int $step): Carbon
    {
        return match ($frequency) {
            'weekly' => $date->addWeeks($step),
            'biweekly' => $date->addWeeks($step * 2),
            'bimonthly' => $date->addMonths($step * 2),
            'quarterly' => $date->addMonths($step * 3),
            'semiannual' => $date->addMonths($step * 6),
            'annual' => $date->addYears($step),
            default => $date->addMonths($step),
        };
    }

    private function countUntilDate(Carbon $baseDate, string $frequency, ?string $endDateStr, int $max): int
    {
        if (! $endDateStr) {
            return $max;
        }

        $endDate = Carbon::parse($endDateStr)->endOfDay();
        $count = 0;

        for ($i = 0; $i < $max; $i++) {
            if ($this->addInterval($baseDate->copy(), $frequency, $i)->greaterThan($endDate)) {
                break;
            }
            $count++;
        }

        return max(1, $count);
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
