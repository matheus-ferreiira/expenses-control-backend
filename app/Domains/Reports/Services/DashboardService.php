<?php

namespace App\Domains\Reports\Services;

use App\Domains\Finance\Enums\TransactionType;
use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\Transaction;
use App\Domains\Goals\Models\Goal;
use App\Domains\Habits\Models\Habit;
use App\Domains\Tasks\Models\Task;
use App\Domains\Calendar\Models\CalendarEvent;
use App\Models\User;
use Carbon\Carbon;

final class DashboardService
{
    public function getDashboard(User $user): array
    {
        $today = today();
        $startOfMonth = $today->copy()->startOfMonth();
        $endOfMonth = $today->copy()->endOfMonth();

        return [
            'tasks' => $this->getTasksSummary($user, $today),
            'habits' => $this->getHabitsSummary($user, $today),
            'finance' => $this->getFinanceSummary($user, $today, $startOfMonth, $endOfMonth),
            'goals' => $this->getGoalsSummary($user),
            'calendar' => $this->getCalendarSummary($user, $today),
        ];
    }

    private function getTasksSummary(User $user, Carbon $today): array
    {
        $base = Task::forUser($user->id)->notArchived();

        return [
            'pending_count' => (clone $base)->pending()->count(),
            'due_today_count' => (clone $base)->dueToday()->count(),
            'overdue_count' => (clone $base)->overdue()->count(),
            'due_today' => (clone $base)->dueToday()->with('labels')->limit(5)->get(),
        ];
    }

    private function getHabitsSummary(User $user, Carbon $today): array
    {
        $habits = Habit::forUser($user->id)->active()
            ->with(['logs' => fn($q) => $q->whereDate('completed_date', $today)])
            ->get();

        $completedToday = $habits->filter(fn($h) => $h->logs->isNotEmpty())->count();

        return [
            'total' => $habits->count(),
            'completed_today' => $completedToday,
            'pending_today' => $habits->count() - $completedToday,
            'completion_rate_today' => $habits->count() > 0
                ? round(($completedToday / $habits->count()) * 100, 1)
                : 0,
        ];
    }

    private function getFinanceSummary(User $user, Carbon $today, Carbon $startOfMonth, Carbon $endOfMonth): array
    {
        $monthTransactions = Transaction::forUser($user->id)
            ->inPeriod($startOfMonth->toDateString(), $endOfMonth->toDateString())
            ->get();

        $income = $monthTransactions->where('type', TransactionType::Income->value)->sum('amount');
        $expenses = $monthTransactions->where('type', TransactionType::Expense->value)->sum('amount');

        $totalBalance = BankAccount::forUser($user->id)->active()->sum('balance');

        return [
            'total_balance' => (float) $totalBalance,
            'month_income' => (float) $income,
            'month_expenses' => (float) $expenses,
            'month_balance' => (float) ($income - $expenses),
            'month' => $today->month,
            'year' => $today->year,
        ];
    }

    private function getGoalsSummary(User $user): array
    {
        $goals = Goal::forUser($user->id)->active()->get();

        return [
            'active_count' => $goals->count(),
            'near_deadline' => $goals->filter(
                fn($g) => $g->target_date && $g->target_date->diffInDays(now()) <= 30
            )->count(),
            'recent' => $goals->sortBy('target_date')->take(3)->values(),
        ];
    }

    private function getCalendarSummary(User $user, Carbon $today): array
    {
        $upcoming = CalendarEvent::forUser($user->id)
            ->upcoming(7)
            ->orderBy('start_date')
            ->limit(5)
            ->get();

        return [
            'upcoming_events' => $upcoming,
            'today_events_count' => CalendarEvent::forUser($user->id)
                ->whereDate('start_date', $today)
                ->count(),
        ];
    }
}
