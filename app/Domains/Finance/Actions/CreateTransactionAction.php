<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\DTOs\TransactionDTO;
use App\Domains\Finance\Enums\TransactionType;
use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateTransactionAction
{
    public function execute(User $user, TransactionDTO $dto): Transaction|array
    {
        if ($dto->totalInstallments && $dto->totalInstallments > 1) {
            return $this->createInstallments($user, $dto);
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
                'is_recurring' => $dto->isRecurring,
                'recurrence_config' => $dto->recurrenceConfig,
            ]);

            $this->updateAccountBalance($transaction);

            return $transaction->load(['category', 'account', 'card']);
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
