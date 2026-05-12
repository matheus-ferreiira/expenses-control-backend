<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Enums\TransactionType;
use App\Domains\Finance\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;

final class FinanceReportService
{
    public function getMonthlySummary(User $user, int $year, int $month): array
    {
        $transactions = Transaction::forUser($user->id)
            ->inMonth($year, $month)
            ->with('category')
            ->get();

        $income = $transactions->where('type', TransactionType::Income->value)->sum('amount');
        $expenses = $transactions->where('type', TransactionType::Expense->value)->sum('amount');

        $expensesByCategory = $transactions
            ->where('type', TransactionType::Expense->value)
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
            'transactions_count' => $transactions->count(),
            'expenses_by_category' => $expensesByCategory,
        ];
    }

    public function getYearlySummary(User $user, int $year): array
    {
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $data = Transaction::forUser($user->id)->inMonth($year, $m)->get();
            $months[$m] = [
                'month' => $m,
                'income' => (float) $data->where('type', TransactionType::Income->value)->sum('amount'),
                'expenses' => (float) $data->where('type', TransactionType::Expense->value)->sum('amount'),
            ];
            $months[$m]['balance'] = $months[$m]['income'] - $months[$m]['expenses'];
        }

        return ['year' => $year, 'months' => array_values($months)];
    }

    public function getCashFlow(User $user, string $startDate, string $endDate): array
    {
        $transactions = Transaction::forUser($user->id)
            ->inPeriod($startDate, $endDate)
            ->orderBy('transaction_date')
            ->get();

        $byDate = $transactions->groupBy(fn($t) => $t->transaction_date->toDateString())
            ->map(function ($group) {
                return [
                    'income' => (float) $group->where('type', TransactionType::Income->value)->sum('amount'),
                    'expenses' => (float) $group->where('type', TransactionType::Expense->value)->sum('amount'),
                ];
            });

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_income' => (float) $transactions->where('type', TransactionType::Income->value)->sum('amount'),
            'total_expenses' => (float) $transactions->where('type', TransactionType::Expense->value)->sum('amount'),
            'by_date' => $byDate,
        ];
    }
}
