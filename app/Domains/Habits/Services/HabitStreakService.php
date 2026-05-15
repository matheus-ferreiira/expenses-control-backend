<?php

namespace App\Domains\Habits\Services;

use App\Domains\Habits\Models\Habit;

final class HabitStreakService
{
    public function getCurrentStreak(Habit $habit): int
    {
        $logs = $habit->relationLoaded('logs')
            ? $habit->logs->sortByDesc('completed_date')->pluck('completed_date')->values()
            : $habit->logs()->orderByDesc('completed_date')->pluck('completed_date');

        if ($logs->isEmpty()) {
            return 0;
        }

        $streak = 0;
        $today = today();
        $checkDate = $logs->first()->isSameDay($today) || $logs->first()->isSameDay($today->copy()->subDay())
            ? $logs->first()
            : null;

        if (! $checkDate) {
            return 0;
        }

        $expected = $checkDate->copy();
        foreach ($logs as $logDate) {
            if ($logDate->isSameDay($expected)) {
                $streak++;
                $expected = $expected->subDay();
            } else {
                break;
            }
        }

        return $streak;
    }

    public function getLongestStreak(Habit $habit): int
    {
        $logs = $habit->relationLoaded('logs')
            ? $habit->logs->sortBy('completed_date')->pluck('completed_date')->values()
            : $habit->logs()->orderBy('completed_date')->pluck('completed_date');

        if ($logs->isEmpty()) {
            return 0;
        }

        $longest = 1;
        $current = 1;

        for ($i = 1; $i < $logs->count(); $i++) {
            $diff = $logs[$i - 1]->diffInDays($logs[$i]);
            if ($diff === 1) {
                $current++;
                $longest = max($longest, $current);
            } else {
                $current = 1;
            }
        }

        return $longest;
    }

    public function getCompletionRate(Habit $habit, int $days = 30): float
    {
        $startDate = today()->subDays($days - 1);

        $completedDays = $habit->relationLoaded('logs')
            ? $habit->logs->filter(fn ($l) => $l->completed_date >= $startDate)->count()
            : $habit->logs()->where('completed_date', '>=', $startDate)->count();

        return round(($completedDays / $days) * 100, 1);
    }
}
