<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Enums\TransactionStatus;
use App\Domains\Finance\Enums\TransactionType;
use App\Domains\Finance\Models\Transaction;
use App\Models\User;

final class FinanceReportService
{
    public function getMonthlySummary(User $user, int $year, int $month): array
    {
        $transactions = Transaction::forUser($user->id)
            ->inMonth($year, $month)
            ->with('category')
            ->get();

        $confirmed = $transactions->where('status', TransactionStatus::Confirmed);
        $pending = $transactions->where('status', TransactionStatus::Pending);

        $income = $confirmed->where('type', TransactionType::Income)->sum('amount');
        $expenses = $confirmed->where('type', TransactionType::Expense)->sum('amount');
        $pendingIncome = $pending->where('type', TransactionType::Income)->sum('amount');
        $pendingExpenses = $pending->where('type', TransactionType::Expense)->sum('amount');

        $expensesByCategory = $confirmed
            ->where('type', TransactionType::Expense)
            ->groupBy('category_id')
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'category' => $first->category?->name ?? 'Uncategorized',
                    'color' => $first->category?->color ?? '#888',
                    'total' => $group->sum('amount'),
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
            'balance' => (float) ($income - $expenses),
            'pending_income' => (float) $pendingIncome,
            'pending_expenses' => (float) $pendingExpenses,
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
            $income = (float) $confirmedData->where('type', TransactionType::Income)->sum('amount');
            $expenses = (float) $confirmedData->where('type', TransactionType::Expense)->sum('amount');
            $months[$m] = [
                'month' => $m,
                'income' => $income,
                'expenses' => $expenses,
                'balance' => $income - $expenses,
                'pending_income' => (float) $pendingData->where('type', TransactionType::Income)->sum('amount'),
                'pending_expenses' => (float) $pendingData->where('type', TransactionType::Expense)->sum('amount'),
            ];
        }

        return ['year' => $year, 'months' => array_values($months)];
    }

    public function getCashFlow(User $user, string $startDate, string $endDate): array
    {
        $transactions = Transaction::forUser($user->id)
            ->inPeriod($startDate, $endDate)
            ->orderBy('transaction_date')
            ->get();

        $confirmedAll = $transactions->where('status', TransactionStatus::Confirmed);

        $byDate = $confirmedAll->groupBy(fn ($t) => $t->transaction_date->toDateString())
            ->map(function ($group) {
                return [
                    'income' => (float) $group->where('type', TransactionType::Income)->sum('amount'),
                    'expenses' => (float) $group->where('type', TransactionType::Expense)->sum('amount'),
                ];
            });

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_income' => (float) $confirmedAll->where('type', TransactionType::Income)->sum('amount'),
            'total_expenses' => (float) $confirmedAll->where('type', TransactionType::Expense)->sum('amount'),
            'by_date' => $byDate,
        ];
    }
}
