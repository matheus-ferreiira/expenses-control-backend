<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Enums\TransactionStatus;
use App\Domains\Finance\Enums\TransactionType;
use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\Transaction;
use Illuminate\Support\Facades\DB;

final class ConfirmTransactionAction
{
    /**
     * Confirm a pending transaction:
     * - Set status → Confirmed
     * - Apply the balance delta to the linked account
     */
    public function execute(Transaction $transaction): Transaction
    {
        if ($transaction->isConfirmed()) {
            return $transaction;
        }

        return DB::transaction(function () use ($transaction) {
            $transaction->update(['status' => TransactionStatus::Confirmed->value]);

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
}
