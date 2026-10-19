<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Enums\TransactionStatus;
use App\Domains\Finance\Enums\TransactionType;
use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

final class FinanceReportService
{
    private function total($transactions, string $kind): float
    {
        $effects = app(FinancialEffects::class);

        return $transactions->sum(fn ($transaction) => $effects->economic($transaction)[$kind]) / 100;
    }

    /** @return list<string> ids of the user's reserve accounts (savings box, investments) */
    private function reserveIds(User $user): array
    {
        return BankAccount::forUser($user->id)->reserve()->pluck('id')->all();
    }

    /**
     * Net money moved into reserve accounts: transfers in from a spending account minus withdrawals back.
     * A transfer is never income or expense, so without this the money put away would not show anywhere.
     */
    private function reserved($transactions, array $reserveIds): float
    {
        $cents = 0;
        foreach ($transactions->where('type', TransactionType::Transfer) as $t) {
            $into = in_array($t->destination_account_id, $reserveIds, true);
            $outOf = in_array($t->account_id, $reserveIds, true);
            if ($into !== $outOf && $t->reconciliation_status !== 'conflict') {
                $cents += ($into ? 1 : -1) * FinancialEffects::cents($t->amount);
            }
        }

        return $cents / 100;
    }

    /**
     * The month's largest confirmed spending, by its economic effect (the user's own share),
     * computed over the whole month — not over the page loaded in the browser (D-14).
     */
    private function largestExpense($confirmed): ?array
    {
        $effects = app(FinancialEffects::class);
        $largest = $confirmed->sortByDesc(fn ($t) => $effects->economic($t)['expense'])->first();
        if (! $largest || $effects->economic($largest)['expense'] === 0) {
            return null;
        }

        return [
            'id' => $largest->id,
            'description' => $largest->description,
            'amount' => $effects->economic($largest)['expense'] / 100,
            'transaction_date' => $largest->transaction_date->toDateString(),
            'category' => $largest->category?->name,
        ];
    }

    /**
     * Income and expenses per day or per month between two dates, with the same economic policy as the
     * monthly report (own share of shared bills, reimbursements and transfers out). Feeds the "Hoje" chart,
     * which used to add raw amounts of up to 500 transactions in the browser (D-11).
     *
     * @return list<array{period: string, income: float, expenses: float}>
     */
    public function getSeries(User $user, string $start, string $end, string $group): array
    {
        $format = $group === 'month' ? 'Y-m' : 'Y-m-d';
        $series = [];
        for ($d = Carbon::parse($start); $d->lte(Carbon::parse($end)); $group === 'month' ? $d->addMonthNoOverflow() : $d->addDay()) {
            $series[$d->format($format)] = ['income' => 0, 'expenses' => 0];
        }
        $effects = app(FinancialEffects::class);
        foreach (Transaction::forUser($user->id)->confirmed()->whereBetween('transaction_date', [$start, $end])->get() as $t) {
            $key = $t->transaction_date->format($format);
            if (! isset($series[$key])) {
                continue;
            }
            $effect = $effects->economic($t);
            $series[$key]['income'] += $effect['income'];
            $series[$key]['expenses'] += $effect['expense'];
        }

        return array_map(fn ($period, $v) => ['period' => $period, 'income' => $v['income'] / 100, 'expenses' => $v['expenses'] / 100], array_keys($series), $series);
    }

    /**
     * Spending per category between two dates (economic, confirmed only), largest first.
     *
     * @return list<array{category_id: ?string, category: string, color: string, total: float}>
     */
    public function getCategoryTotals(User $user, string $start, string $end): array
    {
        $effects = app(FinancialEffects::class);

        return Transaction::forUser($user->id)->confirmed()->where('type', TransactionType::Expense)->whereBetween('transaction_date', [$start, $end])->with('category')->get()
            ->groupBy('category_id')
            ->map(fn ($group) => [
                'category_id' => $group->first()->category_id,
                'category' => $group->first()->category?->name ?? 'Sem categoria',
                'color' => $group->first()->category?->color ?? '#888',
                'total' => $group->sum(fn ($t) => $effects->economic($t)['expense']) / 100,
            ])
            ->filter(fn ($row) => $row['total'] > 0)
            ->sortByDesc('total')->values()->all();
    }

    public function getMonthlySummary(User $user, int $year, int $month): array
    {
        $transactions = Transaction::forUser($user->id)
            ->inMonth($year, $month)
            ->with('category')
            ->get();

        $confirmed = $transactions->where('status', TransactionStatus::Confirmed);
        $pending = $transactions->where('status', TransactionStatus::Pending);

        // Goal contributions (aportes) are SAVINGS, not spending — kept out of
        // "expenses" so the report never punishes the user for saving money.
        $income = $this->total($confirmed, 'income');
        $expenses = $this->total($confirmed, 'expense');
        // Money put away counts as saved too: goal contributions plus the net moved into reserve accounts
        $reserveIds = $this->reserveIds($user);
        $reserved = $this->reserved($confirmed, $reserveIds);
        $saved = $confirmed->where('type', TransactionType::Expense)->whereNotNull('goal_id')->sum('amount') + $reserved;
        $pendingIncome = $this->total($pending, 'income');
        $pendingExpenses = $this->total($pending, 'expense');
        $pendingReserved = $this->reserved($pending, $reserveIds);
        $pendingSaved = $pending->where('type', TransactionType::Expense)->whereNotNull('goal_id')->sum('amount') + $pendingReserved;

        $expensesByCategory = $this->categoryBreakdown($confirmed, 'expense', $expenses);
        $incomeByCategory = $this->categoryBreakdown($confirmed, 'income', $income);

        return [
            'year' => $year,
            'month' => $month,
            'income' => (float) $income,
            'expenses' => (float) $expenses,
            'saved' => round((float) $saved, 2),
            'reserved' => round($reserved, 2),
            'pending_reserved' => round($pendingReserved, 2),
            // Rounded at the API boundary: float subtraction used to leak values like -1557.3000000000002 (D-31)
            'balance' => round($income - $expenses - (float) $saved, 2),
            'pending_income' => (float) $pendingIncome,
            'pending_expenses' => (float) $pendingExpenses,
            'pending_saved' => round((float) $pendingSaved, 2),
            'largest_expense' => $this->largestExpense($confirmed),
            'transactions_count' => $transactions->count(),
            'expenses_by_category' => $expensesByCategory,
            'income_by_category' => $incomeByCategory,
        ];
    }

    private function categoryBreakdown($confirmed, string $kind, float $total)
    {
        return $confirmed
            ->filter(fn ($transaction) => app(FinancialEffects::class)->economic($transaction)[$kind] !== 0)
            ->groupBy('category_id')
            ->map(function ($group) use ($kind, $total) {
                $first = $group->first();
                $categoryTotal = $this->total($group, $kind);

                return [
                    'category_id' => $first->category_id,
                    'category' => $first->category?->name ?? 'Sem categoria',
                    'color' => $first->category?->color ?? '#888',
                    'total' => $categoryTotal,
                    'count' => $group->count(),
                    'percentage' => $total > 0 ? round(($categoryTotal / $total) * 100, 1) : 0,
                ];
            })
            ->values();
    }

    /** Drill-down uses the same confirmed economic effects as the monthly category totals. */
    public function getCategoryTransactions(User $user, int $year, int $month, string $kind, ?string $categoryId, int $page, int $perPage): LengthAwarePaginator
    {
        $effects = app(FinancialEffects::class);
        $transactions = Transaction::forUser($user->id)
            ->inMonth($year, $month)->confirmed()
            ->where('category_id', $categoryId)
            ->orderByDesc('transaction_date')->orderByDesc('id')
            ->get()
            ->filter(fn ($transaction) => $effects->economic($transaction)[$kind] !== 0)
            ->values();
        $items = $transactions->forPage($page, $perPage)->values();
        $items->load(['category', 'account', 'destinationAccount', 'card', 'tags', 'goal']);

        return new LengthAwarePaginator($items, $transactions->count(), $perPage, $page);
    }

    public function getYearlySummary(User $user, int $year): array
    {
        $transactions = Transaction::forUser($user->id)
            ->whereYear('transaction_date', $year)
            ->get();

        $reserveIds = $this->reserveIds($user);
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $data = $transactions->filter(fn ($t) => $t->transaction_date->month === $m);
            $confirmedData = $data->where('status', TransactionStatus::Confirmed);
            $pendingData = $data->where('status', TransactionStatus::Pending);
            $income = $this->total($confirmedData, 'income');
            $expenses = $this->total($confirmedData, 'expense');
            $reserved = $this->reserved($confirmedData, $reserveIds);
            $saved = round((float) $confirmedData->where('type', TransactionType::Expense)->whereNotNull('goal_id')->sum('amount') + $reserved, 2);
            $months[$m] = [
                'month' => $m,
                'income' => $income,
                'expenses' => $expenses,
                'saved' => $saved,
                'reserved' => round($reserved, 2),
                'balance' => round($income - $expenses - $saved, 2),
                'pending_income' => $this->total($pendingData, 'income'),
                'pending_expenses' => $this->total($pendingData, 'expense'),
            ];
        }

        return ['year' => $year, 'months' => array_values($months)];
    }

    public function getCashFlow(User $user, string $startDate, string $endDate): array
    {
        return app(CashFlowSummary::class)->get($user->id, $startDate, $endDate);
    }
}
