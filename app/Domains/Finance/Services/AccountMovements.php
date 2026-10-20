<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Enums\TransactionType;
use App\Domains\Finance\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Money that came in and went out of each account in a month, as the bank statement shows it:
 * income and transfers in, spending, transfers out and card payments. Card purchases stay out
 * (they leave the account only when the statement is paid). Same sources as the balance history:
 * imported statement lines plus confirmed manual transactions.
 */
final class AccountMovements
{
    /** @return list<array{account_id: string, in: float, out: float}> */
    public function forMonth(User $user, int $year, int $month): array
    {
        $start = Carbon::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
        $end = Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();
        $totals = [];
        $add = function (?string $accountId, int $cents) use (&$totals): void {
            if (! $accountId || $cents === 0) {
                return;
            }
            $totals[$accountId] ??= ['in' => 0, 'out' => 0];
            $totals[$accountId][$cents > 0 ? 'in' : 'out'] += abs($cents);
        };

        DB::table('import_sources as s')->join('import_batches as b', 'b.id', '=', 's.batch_id')
            ->where('b.status', 'applied')->where('s.user_id', $user->id)->whereNotNull('s.account_id')
            ->whereBetween('s.posted_on', [$start, $end])->get(['s.account_id', 's.cash_cents'])
            ->each(fn ($source) => $add($source->account_id, (int) $source->cash_cents));

        Transaction::forUser($user->id)->confirmed()->whereNull('import_batch_id')->inPeriod($start, $end)->get()
            ->each(function (Transaction $t) use ($add) {
                if ($t->reconciliation_status === 'conflict') {
                    return;
                }
                $cents = FinancialEffects::cents((string) $t->amount);
                $add($t->account_id, $t->type === TransactionType::Income ? $cents : -$cents);
                if ($t->type === TransactionType::Transfer) {
                    $add($t->destination_account_id, $cents);
                }
            });

        return collect($totals)->map(fn ($v, $id) => ['account_id' => $id, 'in' => $v['in'] / 100, 'out' => $v['out'] / 100])->values()->all();
    }
}
