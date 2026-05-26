<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\DTOs\TransactionDTO;
use App\Domains\Finance\Enums\TransactionStatus;
use App\Domains\Finance\Enums\TransactionType;
use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateTransactionAction
{
    /** Number of months to generate for recurring transactions (5 years). */
    private const RECURRENCE_MONTHS = 60;

    public function execute(User $user, TransactionDTO $dto): Transaction|array
    {
        if ($dto->totalInstallments && $dto->totalInstallments > 1) {
            return $this->createInstallments($user, $dto);
        }

        if ($dto->isRecurring) {
            return $this->createRecurring($user, $dto);
        }

        return DB::transaction(function () use ($user, $dto) {
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'account_id' => $dto->accountId,
                'card_id' => $dto->cardId,
                'category_id' => $dto->categoryId,
                'type' => $dto->type,
                'amount' => $dto->amount,
                'description' => $dto->description,
                'notes' => $dto->notes,
                'transaction_date' => $dto->transactionDate,
                'is_recurring' => false,
                'status' => TransactionStatus::Confirmed->value,
            ]);

            if ($dto->tagIds) {
                $transaction->tags()->sync($dto->tagIds);
            }

            $this->updateAccountBalance($transaction);
            $this->updateStreak($user);

            return $transaction->load(['category', 'account', 'card', 'tags']);
        });
    }

    /**
     * Creates the current occurrence (confirmed) and 59 future occurrences (pending)
     * for a recurring transaction — totalling 5 years.
     * Only the confirmed occurrence affects the account balance.
     */
    private function createRecurring(User $user, TransactionDTO $dto): Transaction
    {
        return DB::transaction(function () use ($user, $dto) {
            $groupId = (string) Str::uuid();
            $baseDate = Carbon::parse($dto->transactionDate);
            $first = null;

            for ($i = 0; $i < self::RECURRENCE_MONTHS; $i++) {
                $status = $i === 0
                    ? TransactionStatus::Confirmed->value
                    : TransactionStatus::Pending->value;

                $transaction = Transaction::create([
                    'user_id' => $user->id,
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
                    'status' => $status,
                ]);

                if ($i === 0) {
                    // Only the first occurrence (confirmed) affects the balance + streak
                    $this->updateAccountBalance($transaction);
                    $this->updateStreak($user);
                    $first = $transaction;
                }
            }

            return $first->load(['category', 'account', 'card', 'tags']);
        });
    }

    private function createInstallments(User $user, TransactionDTO $dto): array
    {
        return DB::transaction(function () use ($user, $dto) {
            $groupId = (string) Str::uuid();
            $installmentAmount = round($dto->amount / $dto->totalInstallments, 2);
            $lastInstallmentAmount = round($dto->amount - ($installmentAmount * ($dto->totalInstallments - 1)), 2);
            $transactions = [];
            $baseDate = Carbon::parse($dto->transactionDate);

            for ($i = 1; $i <= $dto->totalInstallments; $i++) {
                $amount = $i === $dto->totalInstallments ? $lastInstallmentAmount : $installmentAmount;
                $transaction = Transaction::create([
                    'user_id' => $user->id,
                    'account_id' => $dto->accountId,
                    'card_id' => $dto->cardId,
                    'category_id' => $dto->categoryId,
                    'type' => $dto->type,
                    'amount' => $amount,
                    'description' => "{$dto->description} ({$i}/{$dto->totalInstallments})",
                    'notes' => $dto->notes,
                    'transaction_date' => $baseDate->copy()->addMonths($i - 1)->toDateString(),
                    'installment_number' => $i,
                    'total_installments' => $dto->totalInstallments,
                    'installment_group_id' => $groupId,
                    'status' => TransactionStatus::Confirmed->value,
                ]);
                $transactions[] = $transaction;
            }

            // Update account balance once for the full purchase amount
            if ($dto->accountId) {
                $account = BankAccount::find($dto->accountId);
                if ($account) {
                    $delta = $dto->type === TransactionType::Income ? $dto->amount : -$dto->amount;
                    $account->increment('balance', $delta);
                }
            }

            return $transactions;
        });
    }

    /**
     * Update user's transaction streak.
     * - Same day as last: keep streak unchanged.
     * - Yesterday: increment streak.
     * - Any gap: reset to 1.
     */
    private function updateStreak(User $user): void
    {
        $today = Carbon::today();
        $last = $user->last_transaction_date;

        if ($last === null) {
            $user->update(['current_streak' => 1, 'last_transaction_date' => $today->toDateString()]);

            return;
        }

        $lastDate = Carbon::parse($last);

        if ($lastDate->isSameDay($today)) {
            // Already counted today — no change
            return;
        }

        if ($lastDate->isSameDay($today->copy()->subDay())) {
            // Yesterday — extend streak
            $user->update([
                'current_streak' => $user->current_streak + 1,
                'last_transaction_date' => $today->toDateString(),
            ]);
        } else {
            // Gap — reset
            $user->update(['current_streak' => 1, 'last_transaction_date' => $today->toDateString()]);
        }
    }

    private function updateAccountBalance(Transaction $transaction): void
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
