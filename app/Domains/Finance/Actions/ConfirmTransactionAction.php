<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Enums\TransactionStatus;
use App\Domains\Finance\Enums\TransactionType;
use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\Transaction;
use App\Domains\Finance\Services\FinancialEffects;
use App\Domains\Finance\Services\RecoveryRecordGuard;
use App\Domains\Finance\Services\UserClock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ConfirmTransactionAction
{
    /**
     * Confirm a pending transaction with what really happened:
     * - amount/date default to the forecast; a forecast dated in the future needs the real date (≤ today)
     * - Set status → Confirmed
     * - Apply the balance delta to the linked account
     */
    public function execute(Transaction $transaction, ?float $amount = null, ?string $date = null): Transaction
    {
        return DB::transaction(function () use ($transaction, $amount, $date) {
            $transaction = Transaction::forUser($transaction->user_id)->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            if ($transaction->isConfirmed()) {
                return $transaction;
            }
            $date ??= $transaction->transaction_date->toDateString();
            if ($date > self::today($transaction)) {
                throw ValidationException::withMessages(['date' => ['Esta previsão ainda não venceu. Informe a data em que você pagou ou recebeu.']]);
            }
            if ($transaction->import_batch_id && $transaction->account_id) {
                RecoveryRecordGuard::editable($transaction);
            }
            $changes = ['status' => TransactionStatus::Confirmed->value];
            if ($amount !== null && FinancialEffects::cents(number_format($amount, 2, '.', '')) !== FinancialEffects::cents((string) $transaction->amount)) {
                $changes['amount'] = number_format($amount, 2, '.', '');
            }
            if ($date !== $transaction->transaction_date->toDateString()) {
                $changes['transaction_date'] = $date;
            }
            $transaction->update($changes);

            if ($transaction->account_id) {
                $account = BankAccount::find($transaction->account_id);
                if ($account) {
                    $delta = $transaction->type === TransactionType::Income
                        ? $transaction->amount
                        : -$transaction->amount;
                    $account->increment('balance', $delta);
                }
            }

            // For transfers: also credit the destination account
            if ($transaction->type === TransactionType::Transfer && $transaction->destination_account_id) {
                $destination = BankAccount::find($transaction->destination_account_id);
                if ($destination) {
                    $destination->increment('balance', $transaction->amount);
                }
            }

            return $transaction->fresh(['category', 'account', 'card', 'tags']);
        });
    }

    /** "Today" in the owner's timezone (the server runs in UTC). */
    public static function today(Transaction $transaction): string
    {
        return UserClock::today($transaction->user_id);
    }
}
