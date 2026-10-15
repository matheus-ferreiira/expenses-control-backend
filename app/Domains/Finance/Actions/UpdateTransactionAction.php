<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\DTOs\TransactionDTO;
use App\Domains\Finance\Enums\TransactionStatus;
use App\Domains\Finance\Enums\TransactionType;
use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\Transaction;
use App\Domains\Finance\Services\FinancialEffects;
use App\Domains\Finance\Services\RecoveryRecordGuard;
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
        // Imported records keep their cash facts, but can always be classified.
        if ($transaction->import_batch_id !== null && $this->onlyClassification($transaction, $dto)) {
            return DB::transaction(fn () => $this->classify($transaction, $dto));
        }
        RecoveryRecordGuard::editable($transaction, $scope);

        return DB::transaction(function () use ($transaction, $dto, $scope) {
            if ($scope === 'this_and_future' || $scope === 'all') {
                return $this->executeBulk($transaction, $dto, $scope);
            }

            return $this->executeSingle($transaction, $dto);
        });
    }

    // ── Classification of imported records (category, tags, notes) ───────────

    /** True when the edit leaves every cash fact (value, date, accounts, card, type, text) untouched. */
    private function onlyClassification(Transaction $transaction, TransactionDTO $dto): bool
    {
        return $dto->type === $transaction->type
            && FinancialEffects::cents(number_format($dto->amount, 2, '.', '')) === FinancialEffects::cents((string) $transaction->amount)
            && $dto->transactionDate === $transaction->transaction_date->toDateString()
            && $dto->description === $transaction->description
            && $dto->accountId === $transaction->account_id
            && $dto->cardId === $transaction->card_id
            && ($dto->type !== TransactionType::Transfer || $dto->destinationAccountId === $transaction->destination_account_id)
            && $dto->goalId === $transaction->goal_id
            && $dto->isRecurring === (bool) $transaction->is_recurring;
    }

    private function classify(Transaction $transaction, TransactionDTO $dto): Transaction
    {
        $changes = ['category_id' => $dto->categoryId, 'notes' => $dto->notes];
        // A record imported as "unknown" that the user files under a category becomes a real income/expense.
        if ($dto->categoryId && in_array($transaction->economic_kind, [null, 'unknown'], true) && $transaction->type !== TransactionType::Transfer) {
            $cents = FinancialEffects::cents((string) $transaction->amount);
            $changes += [
                'economic_kind' => $transaction->type->value,
                'classification_status' => 'identified',
                'economic_income_cents' => $transaction->type === TransactionType::Income ? $cents : 0,
                'economic_expense_cents' => $transaction->type === TransactionType::Expense ? $cents : 0,
            ];
        } elseif ($dto->categoryId && $transaction->classification_status === 'pending_category') {
            $changes['classification_status'] = 'identified';
        }
        $transaction->forceFill($changes)->save();
        if ($dto->tagIds !== null) {
            $transaction->tags()->sync($dto->tagIds);
        }

        return $transaction->load(['category', 'account', 'card', 'tags']);
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
            'destination_account_id' => $dto->destinationAccountId,
            'card_id' => $dto->cardId,
            'category_id' => $dto->categoryId,
            'goal_id' => $dto->goalId,
            'type' => $dto->type,
            'amount' => $dto->amount,
            'description' => $dto->description,
            'notes' => $dto->notes,
            'transaction_date' => $dto->transactionDate,
            'is_recurring' => $dto->isRecurring,
            'recurrence_config' => $this->recurrenceConfig($transaction, $dto),
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

        $horizon = Carbon::today()->addMonthsNoOverflow(CreateTransactionAction::HORIZON_MONTHS);

        // Start at i=1 — i=0 is the promoted transaction already saved
        for ($i = 1; $i < $total; $i++) {
            $occurrenceDate = $this->addInterval($baseDate->copy(), $frequency, $i);

            // Rolling window — see CreateTransactionAction::HORIZON_MONTHS
            if ($occurrenceDate->greaterThan($horizon)) {
                break;
            }

            Transaction::create([
                'user_id' => $transaction->user_id,
                'account_id' => $dto->accountId,
                'card_id' => $dto->cardId,
                'category_id' => $dto->categoryId,
                'goal_id' => $dto->goalId,
                'type' => $dto->type,
                'amount' => $dto->amount,
                'description' => $dto->description,
                'notes' => $dto->notes,
                'transaction_date' => $occurrenceDate->toDateString(),
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
            'bimonthly' => $date->addMonthsNoOverflow($step * 2),
            'quarterly' => $date->addMonthsNoOverflow($step * 3),
            'semiannual' => $date->addMonthsNoOverflow($step * 6),
            'annual' => $date->addYearsNoOverflow($step),
            default => $date->addMonthsNoOverflow($step),
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
        if (! $transaction->installment_group_id && ! $transaction->recurrence_group_id) {
            return $this->executeSingle($transaction, $dto);
        }
        // Installment group takes priority if this transaction belongs to one
        if ($transaction->installment_group_id) {
            $query = Transaction::forUser($transaction->user_id)->where('installment_group_id', $transaction->installment_group_id);

            if ($scope === 'this_and_future') {
                $query->where('installment_number', '>=', $transaction->installment_number);
            }
        } else {
            $query = Transaction::forUser($transaction->user_id)->where('recurrence_group_id', $transaction->recurrence_group_id);

            if ($scope === 'this_and_future') {
                $query->where('transaction_date', '>=', $transaction->transaction_date->toDateString());
            }
        }

        // History is never rewritten: besides the selected occurrence, only unpaid ones change
        // ("Todas" = every future unpaid occurrence).
        $affected = $query->get()->filter(fn (Transaction $o) => $o->id === $transaction->id || ! $o->isConfirmed());
        $today = Carbon::today();
        $newBaseDate = Carbon::parse($dto->transactionDate);
        // Weekly/biweekly series move by the same number of days, keeping their own pattern.
        $frequency = ($dto->recurrenceConfig ?? $transaction->recurrence_config ?? [])['frequency'] ?? 'monthly';
        $shiftDays = in_array($frequency, ['weekly', 'biweekly'], true)
            ? (int) $transaction->transaction_date->copy()->startOfDay()->diffInDays($newBaseDate->copy()->startOfDay(), false)
            : null;

        foreach ($affected as $occurrence) {
            // Reverse the old balance contribution before updating
            $this->reverseAccountBalance($occurrence);

            // Determine the new date for this occurrence.
            // For the selected occurrence use the date from the DTO.
            // For other occurrences in a recurring series, shift the day-of-month
            // to match the new base date while preserving each occurrence's year/month.
            // Installment occurrences keep their original dates (installment number drives order, not day).
            if ($occurrence->id === $transaction->id) {
                $newDate = $dto->transactionDate;
            } elseif (! $transaction->installment_group_id && $shiftDays !== null) {
                $newDate = $occurrence->transaction_date->copy()->addDays($shiftDays)->toDateString();
            } elseif (! $transaction->installment_group_id) {
                $occDate = $occurrence->transaction_date->copy();
                $newDate = $occDate->setDay(min($newBaseDate->day, $occDate->daysInMonth))->toDateString();
            } else {
                $newDate = $occurrence->transaction_date->toDateString();
            }

            // If the occurrence was confirmed but the new date falls in the future,
            // demote it to pending so the balance reversal (done above) is permanent.
            $newStatus = $occurrence->status;
            if ($occurrence->isConfirmed() && Carbon::parse($newDate)->greaterThan($today)) {
                $newStatus = TransactionStatus::Pending;
            }

            $updateData = [
                'account_id' => $dto->accountId,
                'destination_account_id' => $dto->destinationAccountId,
                'card_id' => $dto->cardId,
                'category_id' => $dto->categoryId,
                'goal_id' => $dto->goalId,
                'type' => $dto->type,
                'amount' => $dto->amount,
                'description' => $dto->description,
                'notes' => $dto->notes,
                'transaction_date' => $newDate,
                'is_recurring' => $dto->isRecurring,
                'recurrence_config' => $this->recurrenceConfig($occurrence, $dto),
                'status' => $newStatus,
            ];

            $occurrence->update($updateData);

            if ($dto->tagIds !== null) {
                $occurrence->tags()->sync($dto->tagIds);
            }

            // Re-apply balance only if the occurrence is still confirmed after the update.
            $this->applyAccountBalance($occurrence);
        }

        return $transaction->fresh(['category', 'account', 'card', 'tags']);
    }

    /**
     * The series configuration survives edits that do not send it (e.g. changing only the amount).
     * It is cleared only when the transaction stops being recurring.
     */
    private function recurrenceConfig(Transaction $transaction, TransactionDTO $dto): ?array
    {
        if (! $dto->isRecurring) {
            return null;
        }

        return $dto->recurrenceConfig ?? $transaction->recurrence_config;
    }

    // ── Balance helpers ───────────────────────────────────────────────────────

    private function reverseAccountBalance(Transaction $transaction): void
    {
        // Pending transactions never affected the balance — nothing to reverse
        if (! $transaction->isConfirmed() || ! $transaction->account_id) {
            return;
        }

        $account = BankAccount::find($transaction->account_id);
        if ($account) {
            $delta = $transaction->type === TransactionType::Income
                ? -$transaction->amount
                : $transaction->amount;
            $account->increment('balance', $delta);
        }

        // For transfers: also reverse the destination account credit
        if ($transaction->type === TransactionType::Transfer && $transaction->destination_account_id) {
            $destination = BankAccount::find($transaction->destination_account_id);
            if ($destination) {
                $destination->decrement('balance', $transaction->amount);
            }
        }
    }

    private function applyAccountBalance(Transaction $transaction): void
    {
        // Only confirmed transactions affect the balance
        if (! $transaction->isConfirmed() || ! $transaction->account_id) {
            return;
        }

        $account = BankAccount::find($transaction->account_id);
        if ($account) {
            $delta = $transaction->type === TransactionType::Income
                ? $transaction->amount
                : -$transaction->amount;
            $account->increment('balance', $delta);
        }

        // For transfers: also credit the destination account
        if ($transaction->type === TransactionType::Transfer && $transaction->destination_account_id) {
            $destination = BankAccount::find($transaction->destination_account_id);
            if ($destination) {
                $destination->increment('balance', $transaction->amount);
            }
        }
    }
}
