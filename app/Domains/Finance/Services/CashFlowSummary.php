<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\Transaction;
use Illuminate\Support\Facades\DB;

final class CashFlowSummary
{
    public function get(string $userId, string $start, string $end): array
    {
        $days = [];
        $add = function (string $date, int $cents) use (&$days): void {
            $days[$date] ??= ['income' => 0, 'expenses' => 0];
            $days[$date][$cents >= 0 ? 'income' : 'expenses'] += abs($cents);
        };
        $sources = DB::table('import_sources as s')->join('import_batches as b', 'b.id', '=', 's.batch_id')->where('b.status', 'applied')->where('s.user_id', $userId)->whereNotNull('s.account_id')->whereBetween('s.posted_on', [$start, $end])->get(['s.posted_on', 's.cash_cents']);
        foreach ($sources as $source) {
            $add($source->posted_on, (int) $source->cash_cents);
        }
        // Reserve accounts sit outside the cash flow: moving money into the savings box is an outflow of available cash
        $reserveIds = BankAccount::forUser($userId)->reserve()->pluck('id')->all();
        $manual = Transaction::forUser($userId)->confirmed()->whereNull('import_batch_id')->inPeriod($start, $end)->get();
        foreach ($manual as $transaction) {
            if ($transaction->reconciliation_status === 'conflict') {
                continue;
            }
            $cents = FinancialEffects::cents($transaction->amount);
            if ($transaction->account_id && ! in_array($transaction->account_id, $reserveIds, true)) {
                $add($transaction->transaction_date->toDateString(), $transaction->type->value === 'income' ? $cents : -$cents);
            }
            if ($transaction->destination_account_id && $transaction->type->value === 'transfer' && ! in_array($transaction->destination_account_id, $reserveIds, true)) {
                $add($transaction->transaction_date->toDateString(), $cents);
            }
        }
        ksort($days);
        $income = array_sum(array_column($days, 'income'));
        $expenses = array_sum(array_column($days, 'expenses'));
        foreach ($days as &$day) {
            $day['income'] /= 100;
            $day['expenses'] /= 100;
        }

        return ['start_date' => $start, 'end_date' => $end, 'total_income' => $income / 100, 'total_expenses' => $expenses / 100, 'by_date' => $days];
    }
}
