<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Enums\TransactionType;
use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\Transaction;
use Illuminate\Support\Facades\DB;

final class AccountBalanceHistory
{
    public function at(BankAccount $account, string $date): array
    {
        $opening = DB::table('account_balance_checkpoints')->where('user_id', $account->user_id)->where('account_id', $account->id)->where('kind', 'opening')->where('observed_on', '<=', $date)->orderByDesc('observed_on')->first();
        if (! $opening) {
            return ['account_id' => $account->id, 'date' => $date, 'balance_cents' => null, 'status' => 'unverified', 'current_recorded_balance' => $account->balance];
        }
        $sources = DB::table('import_sources as s')->join('import_batches as b', 'b.id', '=', 's.batch_id')->where('b.status', 'applied')->where('s.user_id', $account->user_id)->where('s.account_id', $account->id)->whereBetween('s.posted_on', [$opening->observed_on, $date])->sum('s.cash_cents');
        $manual = Transaction::forUser($account->user_id)->confirmed()->whereNull('import_batch_id')->whereBetween('transaction_date', [$opening->observed_on, $date])->where(fn ($q) => $q->where('account_id', $account->id)->orWhere('destination_account_id', $account->id))->get();
        $balance = (int) $opening->balance_cents + (int) $sources;
        foreach ($manual as $transaction) {
            if ($transaction->reconciliation_status === 'conflict') {
                continue;
            }
            $cents = FinancialEffects::cents($transaction->amount);
            if ($transaction->account_id === $account->id) {
                $balance += $transaction->type === TransactionType::Income ? $cents : -$cents;
            }
            if ($transaction->destination_account_id === $account->id && $transaction->type === TransactionType::Transfer) {
                $balance += $cents;
            }
        }

        return ['account_id' => $account->id, 'date' => $date, 'balance_cents' => $balance, 'status' => 'reconstructed', 'opening_date' => $opening->observed_on];
    }
}
