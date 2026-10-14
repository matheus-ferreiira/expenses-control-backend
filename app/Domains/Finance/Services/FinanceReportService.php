<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Enums\TransactionStatus;
use App\Domains\Finance\Enums\TransactionType;
use App\Domains\Finance\Models\Transaction;
use App\Models\User;

final class FinanceReportService
{
    private function total($transactions, string $kind): float
    {
        $effects = app(FinancialEffects::class);

        return $transactions->sum(fn ($transaction) => $effects->economic($transaction)[$kind]) / 100;
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
        $saved = $confirmed->where('type', TransactionType::Expense)->whereNotNull('goal_id')->sum('amount');
        $pendingIncome = $this->total($pending, 'income');
        $pendingExpenses = $this->total($pending, 'expense');
        $pendingSaved = $pending->where('type', TransactionType::Expense)->whereNotNull('goal_id')->sum('amount');

        $expensesByCategory = $confirmed
            ->filter(fn ($transaction) => app(FinancialEffects::class)->economic($transaction)['expense'] !== 0)
            ->groupBy('category_id')
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'category' => $first->category?->name ?? 'Sem categoria',
                    'color' => $first->category?->color ?? '#888',
                    'total' => $this->total($group, 'expense'),
                    'count' => $group->count(),
                    'percentage' => 0,
                ];
            })
            ->values();

        if ($expenses > 0) {
            $expensesByCategory = $expensesByCategory->map(function ($item) use ($expenses) {
                $item['percentage'] = round(($item['total'] / $expenses) * 100, 1);

                return $item;
            });
        }

        return [
            'year' => $year,
            'month' => $month,
            'income' => (float) $income,
            'expenses' => (float) $expenses,
            'saved' => (float) $saved,
            'balance' => (float) ($income - $expenses - $saved),
            'pending_income' => (float) $pendingIncome,
            'pending_expenses' => (float) $pendingExpenses,
            'pending_saved' => (float) $pendingSaved,
            'transactions_count' => $transactions->count(),
            'expenses_by_category' => $expensesByCategory,
        ];
    }

    public function getYearlySummary(User $user, int $year): array
    {
        $transactions = Transaction::forUser($user->id)
            ->whereYear('transaction_date', $year)
            ->get();

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $data = $transactions->filter(fn ($t) => $t->transaction_date->month === $m);
            $confirmedData = $data->where('status', TransactionStatus::Confirmed);
            $pendingData = $data->where('status', TransactionStatus::Pending);
            $income = $this->total($confirmedData, 'income');
            $expenses = $this->total($confirmedData, 'expense');
            $saved = (float) $confirmedData->where('type', TransactionType::Expense)->whereNotNull('goal_id')->sum('amount');
            $months[$m] = [
                'month' => $m,
                'income' => $income,
                'expenses' => $expenses,
                'saved' => $saved,
                'balance' => $income - $expenses - $saved,
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
