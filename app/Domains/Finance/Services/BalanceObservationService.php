<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Enums\TransactionType;
use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Conferir saldo": the balance the user sees in the bank, recorded without creating income or expense (D-42).
 * The first check of an account that has no verified opening (e.g. a wallet) becomes its opening.
 */
final class BalanceObservationService
{
    public function __construct(private readonly AccountBalanceHistory $history) {}

    public function observe(BankAccount $account, string $date, string $balance, string $evidence): array
    {
        $cents = FinancialEffects::cents($balance);

        return DB::transaction(function () use ($account, $date, $cents, $evidence) {
            $hasOpening = DB::table('account_balance_checkpoints')->where('account_id', $account->id)->where('kind', 'opening')->where('observed_on', '<=', $date)->exists();
            if (! $hasOpening) {
                // The observed value already includes the day's movements; the opening is the start of that day.
                $opening = $cents - $this->sameDayNet($account, $date);
                $this->insert($account, $date, $opening, 'opening', $evidence);
                $recorded = FinancialEffects::cents(number_format((float) $account->balance, 2, '.', ''));
                if ($date === UserClock::today($account->user_id)) {
                    // Nothing to rebuild this account from: its recorded balance becomes what the user saw.
                    $account->update(['balance' => FinancialEffects::decimal($cents)]);
                }

                return ['observed_cents' => $cents, 'calculated_cents' => $recorded, 'difference_cents' => $cents - $recorded, 'opening_created' => true];
            }
            $calculated = $this->history->at($account, $date)['balance_cents'];
            $this->insert($account, $date, $cents, 'observation', $evidence);

            return ['observed_cents' => $cents, 'calculated_cents' => $calculated, 'difference_cents' => $calculated === null ? null : $cents - $calculated, 'opening_created' => false];
        });
    }

    /** Latest checks of the account, each compared with the balance the app calculates for that date. */
    public function history(BankAccount $account, int $limit = 20): array
    {
        return DB::table('account_balance_checkpoints')->where('account_id', $account->id)->whereIn('kind', ['opening', 'observation'])
            ->orderByDesc('observed_on')->orderByDesc('created_at')->limit($limit)->get()
            ->map(function ($row) use ($account) {
                $calculated = $row->kind === 'observation' ? $this->history->at($account, $row->observed_on)['balance_cents'] : null;

                return [
                    'id' => $row->id,
                    'kind' => $row->kind,
                    'observed_on' => $row->observed_on,
                    'observed_cents' => (int) $row->balance_cents,
                    'calculated_cents' => $calculated,
                    'difference_cents' => $calculated === null ? null : (int) $row->balance_cents - $calculated,
                    'recorded_at' => $row->created_at,
                ];
            })->all();
    }

    private function sameDayNet(BankAccount $account, string $date): int
    {
        return Transaction::forUser($account->user_id)->confirmed()->whereNull('import_batch_id')->where('transaction_date', $date)
            ->where(fn ($q) => $q->where('account_id', $account->id)->orWhere('destination_account_id', $account->id))->get()
            ->sum(function (Transaction $t) use ($account) {
                $cents = FinancialEffects::cents((string) $t->amount);
                $net = $t->account_id === $account->id ? ($t->type === TransactionType::Income ? $cents : -$cents) : 0;

                return $net + ($t->destination_account_id === $account->id && $t->type === TransactionType::Transfer ? $cents : 0);
            });
    }

    private function insert(BankAccount $account, string $date, int $cents, string $kind, string $evidence): void
    {
        DB::table('account_balance_checkpoints')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $account->user_id, 'account_id' => $account->id,
            'observed_on' => $date, 'balance_cents' => $cents, 'kind' => $kind, 'evidence' => $evidence,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
