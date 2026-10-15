<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\DTOs\BankAccountDTO;
use App\Domains\Finance\Enums\TransactionType;
use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\CreditCard;
use App\Domains\Finance\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class BankAccountService
{
    public function list(User $user): Collection
    {
        // Return ALL accounts (active + archived) so the frontend can show archived separately.
        // The frontend filters via activeAccounts/archivedAccounts computed properties.
        return BankAccount::forUser($user->id)->with('creditCards')->get();
    }

    public function create(User $user, BankAccountDTO $dto): BankAccount
    {
        return BankAccount::create([
            'user_id' => $user->id,
            'name' => $dto->name,
            'type' => $dto->type,
            'bank_name' => $dto->bankName,
            'balance' => $dto->balance,
            'currency' => $dto->currency,
            'color' => $dto->color,
            'is_active' => $dto->isActive,
        ]);
    }

    public function update(BankAccount $account, BankAccountDTO $dto): BankAccount
    {
        $account->update([
            'name' => $dto->name,
            'type' => $dto->type,
            'bank_name' => $dto->bankName,
            'color' => $dto->color,
            'is_active' => $dto->isActive,
        ]);

        return $account;
    }

    public function delete(BankAccount $account): void
    {
        $account->delete();
    }

    public function getHistoricalBalance(User $user, int $year, int $month): array
    {
        $accounts = BankAccount::forUser($user->id)->active()->get();
        if (DB::table('account_balance_checkpoints')->where('user_id', $user->id)->where('kind', 'opening')->exists()) {
            $date = Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();
            $balances = $accounts->map(fn ($account) => app(AccountBalanceHistory::class)->at($account, $date));
            $known = $balances->whereNotNull('balance_cents')->sum('balance_cents');
            // Scheduled statement payments are covered by the statements themselves (below).
            $pending = Transaction::forUser($user->id)->pending()->whereNotNull('account_id')->where('transaction_date', '>=', today()->toDateString())->where('transaction_date', '<=', $date)
                ->where(fn ($q) => $q->where('type', '!=', TransactionType::Transfer->value)->orWhereNull('card_id'))->get();
            $delta = $pending->sum(fn ($t) => FinancialEffects::cents($t->amount) * ($t->type === TransactionType::Income ? 1 : -1) + ($t->type === TransactionType::Transfer && $t->destination_account_id ? FinancialEffects::cents($t->amount) : 0));
            $cards = $this->cardStatementsDue($user, $date);

            return ['balance' => $known / 100, 'projected_balance' => ($known + $delta - $cards['cents']) / 100, 'card_due' => $cards['cents'] / 100, 'card_statements' => $cards['items'], 'accounts' => $balances, 'unverified_accounts' => $balances->whereNull('balance_cents')->pluck('account_id')];
        }
        $currentBalance = (float) $accounts->sum('balance');
        $today = Carbon::today();

        $endOfMonth = Carbon::createFromDate($year, $month, 1)->endOfMonth();
        $isFutureMonth = ($year > $today->year) || ($year === $today->year && $month > $today->month);

        $incomeAfter = (float) Transaction::forUser($user->id)
            ->confirmed()
            ->where('type', TransactionType::Income)
            ->whereNull('card_id')
            ->where('transaction_date', '>', $endOfMonth->toDateString())
            ->sum('amount');

        $expenseAfter = (float) Transaction::forUser($user->id)
            ->confirmed()
            ->where('type', TransactionType::Expense)
            ->whereNull('card_id')
            ->where('transaction_date', '>', $endOfMonth->toDateString())
            ->sum('amount');

        $historicalBalance = $currentBalance - $incomeAfter + $expenseAfter;

        // Card purchases never leave an account directly: they are covered by the card statements below.
        if ($isFutureMonth) {
            // For future months: accumulate ALL pending transactions from today through
            // the end of the target month so that navigating further forward correctly
            // deducts each intermediate month's pending (e.g. July includes June+July pending).
            $pendingIncome = (float) Transaction::forUser($user->id)
                ->pending()
                ->where('type', TransactionType::Income)
                ->whereNull('card_id')
                ->where('transaction_date', '>=', $today->toDateString())
                ->where('transaction_date', '<=', $endOfMonth->toDateString())
                ->sum('amount');

            $pendingExpense = (float) Transaction::forUser($user->id)
                ->pending()
                ->where('type', TransactionType::Expense)
                ->whereNull('card_id')
                ->where('transaction_date', '>=', $today->toDateString())
                ->where('transaction_date', '<=', $endOfMonth->toDateString())
                ->sum('amount');
        } else {
            // For current/past months: only count pending within that specific month.
            $pendingIncome = (float) Transaction::forUser($user->id)
                ->pending()
                ->where('type', TransactionType::Income)
                ->whereNull('card_id')
                ->inMonth($year, $month)
                ->sum('amount');

            $pendingExpense = (float) Transaction::forUser($user->id)
                ->pending()
                ->where('type', TransactionType::Expense)
                ->whereNull('card_id')
                ->inMonth($year, $month)
                ->sum('amount');
        }
        $cards = $this->cardStatementsDue($user, $endOfMonth->toDateString());

        return [
            'balance' => $historicalBalance,
            'projected_balance' => $historicalBalance + $pendingIncome - $pendingExpense - $cards['cents'] / 100,
            'card_due' => $cards['cents'] / 100,
            'card_statements' => $cards['items'],
        ];
    }

    /**
     * What is still owed on card statements due up to $until (including overdue ones).
     * Only applies to the present and the future: past months are already settled history.
     *
     * @return array{cents: int, items: list<array{card_id: string, card_name: string, cycle: string, due_on: string, remaining_cents: int}>}
     */
    private function cardStatementsDue(User $user, string $until): array
    {
        $today = Carbon::now($user->timezone ?: 'America/Sao_Paulo')->toDateString();
        if ($until < $today) {
            return ['cents' => 0, 'items' => []];
        }
        $items = [];
        foreach (CreditCard::forUser($user->id)->where('is_active', true)->get() as $card) {
            foreach (app(CardOverview::class)->statements($card) as $statement) {
                if ($statement['remaining_cents'] > 0 && $statement['due_on'] <= $until) {
                    $items[] = ['card_id' => $card->id, 'card_name' => $card->name, 'cycle' => $statement['cycle'], 'due_on' => $statement['due_on'], 'remaining_cents' => $statement['remaining_cents']];
                }
            }
        }

        return ['cents' => array_sum(array_column($items, 'remaining_cents')), 'items' => $items];
    }

    public function getConsolidatedBalance(User $user): array
    {
        $accounts = BankAccount::forUser($user->id)->active()->get();

        $totalBalance = $accounts->sum('balance');
        $byCurrency = $accounts->groupBy('currency')->map(fn ($group) => $group->sum('balance'));

        return [
            'total' => $totalBalance,
            'by_currency' => $byCurrency,
            'accounts' => $accounts->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'balance' => $a->balance]),
        ];
    }

    public function createCreditCard(BankAccount $account, array $data): CreditCard
    {
        return $account->creditCards()->create([
            'user_id' => $account->user_id,
            ...$data,
            'is_active' => true,
        ]);
    }

    public function updateCreditCard(CreditCard $card, array $data): CreditCard
    {
        $card->update($data);

        return $card;
    }

    public function deleteCreditCard(CreditCard $card): void
    {
        $card->delete();
    }
}
