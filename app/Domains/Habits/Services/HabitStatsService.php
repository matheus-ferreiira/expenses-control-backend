<?php

namespace App\Domains\Habits\Services;

use App\Domains\Habits\Models\Habit;
use Carbon\Carbon;

final class HabitStatsService
{
    public function __construct(
        private readonly HabitStreakService $streakService,
    ) {}

    public function getStats(Habit $habit): array
    {
        $today = today();
        $startOfWeek = $today->copy()->startOfWeek();
        $startOfMonth = $today->copy()->startOfMonth();

        $completedThisWeek = $habit->logs()
            ->where('completed_date', '>=', $startOfWeek)
            ->count();

        $completedThisMonth = $habit->logs()
            ->where('completed_date', '>=', $startOfMonth)
            ->count();

        $totalCompleted = $habit->logs()->count();

        $completedToday = $habit->logs()
            ->whereDate('completed_date', $today)
            ->exists();

        return [
            'current_streak' => $this->streakService->getCurrentStreak($habit),
            'longest_streak' => $this->streakService->getLongestStreak($habit),
            'completion_rate_30d' => $this->streakService->getCompletionRate($habit, 30),
            'completion_rate_7d' => $this->streakService->getCompletionRate($habit, 7),
            'completed_today' => $completedToday,
            'completed_this_week' => $completedThisWeek,
            'completed_this_month' => $completedThisMonth,
            'total_completed' => $totalCompleted,
        ];
    }

    public function getHeatmap(Habit $habit, int $days = 365): array
    {
        $startDate = today()->subDays($days - 1);

        $logs = $habit->logs()
            ->where('completed_date', '>=', $startDate)
            ->pluck('completed_date')
            ->map(fn($date) => $date->toDateString())
            ->flip()
            ->map(fn() => true)
            ->toArray();

        $heatmap = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i)->toDateString();
            $heatmap[$date] = isset($logs[$date]);
        }

        return $heatmap;
    }
}
