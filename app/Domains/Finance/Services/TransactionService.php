<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Actions\ConfirmTransactionAction;
use App\Domains\Finance\Actions\CreateTransactionAction;
use App\Domains\Finance\Actions\UpdateTransactionAction;
use App\Domains\Finance\DTOs\TransactionDTO;
use App\Domains\Finance\Enums\TransactionType;
use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\Transaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class TransactionService
{
    public function __construct(
        private readonly CreateTransactionAction $createTransaction,
        private readonly UpdateTransactionAction $updateTransaction,
        private readonly ConfirmTransactionAction $confirmTransaction,
    ) {}

    public function list(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Transaction::forUser($user->id)
            ->with(['category', 'account', 'card', 'tags']);

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['account_id'])) {
            $query->where('account_id', $filters['account_id']);
        }

        if (! empty($filters['card_id'])) {
            $query->where('card_id', $filters['card_id']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['start_date'])) {
            $query->where('transaction_date', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->where('transaction_date', '<=', $filters['end_date']);
        }

        if (! empty($filters['month']) && ! empty($filters['year'])) {
            $query->inMonth((int) $filters['year'], (int) $filters['month']);
        }

        if (! empty($filters['search'])) {
            $query->where('description', 'like', "%{$filters['search']}%");
        }

        // Filter by fix (recurring) transactions
        if (array_key_exists('is_recurring', $filters) && $filters['is_recurring'] !== null && $filters['is_recurring'] !== '') {
            $query->where('is_recurring', (bool) $filters['is_recurring']);
        }

        // Filter by status (confirmed | pending)
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $allowedSorts = ['transaction_date', 'amount', 'description', 'created_at'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSorts)
            ? $filters['sort_by']
            : 'transaction_date';
        $sortDir = ($filters['sort_direction'] ?? '') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir);

        return $query->paginate($filters['per_page'] ?? 20);
    }

    public function create(User $user, TransactionDTO $dto): mixed
    {
        return $this->createTransaction->execute($user, $dto);
    }

    public function update(Transaction $transaction, TransactionDTO $dto, string $scope = 'this_only'): Transaction
    {
        return $this->updateTransaction->execute($transaction, $dto, $scope);
    }

    public function confirm(Transaction $transaction): Transaction
    {
        return $this->confirmTransaction->execute($transaction);
    }

    public function delete(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            if ($transaction->recurrence_group_id) {
                // Cascade-delete all occurrences; only reverse balance for confirmed ones
                /** @var Collection<int, Transaction> $group */
                $group = Transaction::where('recurrence_group_id', $transaction->recurrence_group_id)->get();

                foreach ($group as $occurrence) {
                    if ($occurrence->isConfirmed() && $occurrence->account_id) {
                        $account = BankAccount::find($occurrence->account_id);
                        if ($account) {
                            $delta = $occurrence->type === TransactionType::Income
                                ? -$occurrence->amount
                                : $occurrence->amount;
                            $account->increment('balance', $delta);
                        }
                    }
                    $occurrence->delete();
                }

                return;
            }

            // Normal single-transaction delete — only reverse balance if confirmed
            if ($transaction->isConfirmed() && $transaction->account_id) {
                $account = BankAccount::find($transaction->account_id);
                if ($account) {
                    $delta = $transaction->type === TransactionType::Income
                        ? -$transaction->amount
                        : $transaction->amount;
                    $account->increment('balance', $delta);
                }
            }

            $transaction->delete();
        });
    }
}
