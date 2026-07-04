<?php

namespace App\Domains\Tasks\Services;

use App\Domains\Tasks\Enums\RecurrenceType;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Calculates next occurrence dates for recurring tasks.
 *
 * recurrence_config schema:
 *   interval      (int)   — number of units between occurrences (default: 1)
 *   days_of_week  (int[]) — for weekly: 0=Sun … 6=Sat (empty = every N weeks from same weekday)
 *   day_of_month  (int)   — for monthly: 1-31 (absent = same day of month)
 *   interval_unit (string)— for custom: "days"|"weeks"|"months"|"years"
 */
final class RecurrenceService
{
    public function calculateNextDate(RecurrenceType $type, array $config, Carbon $from): Carbon
    {
        $next = match ($type) {
            RecurrenceType::Daily => $this->nextDaily($from, $config),
            RecurrenceType::Weekly => $this->nextWeekly($from, $config),
            RecurrenceType::Monthly => $this->nextMonthly($from, $config),
            RecurrenceType::Yearly => $this->nextYearly($from, $config),
            RecurrenceType::Weekdays => $this->nextWeekday($from),
            RecurrenceType::Custom => $this->nextCustom($from, $config),
            RecurrenceType::None => throw new InvalidArgumentException('Non-recurring task has no next date'),
        };

        // O horário do dia faz parte da rotina ("acordar 08h" repete às 08h)
        return $next->setTime($from->hour, $from->minute, (int) $from->second);
    }

    private function nextDaily(Carbon $from, array $config): Carbon
    {
        $interval = max(1, (int) ($config['interval'] ?? 1));

        return $from->copy()->addDays($interval)->startOfDay();
    }

    private function nextWeekly(Carbon $from, array $config): Carbon
    {
        $interval = max(1, (int) ($config['interval'] ?? 1));
        $days = array_map('intval', (array) ($config['days_of_week'] ?? []));

        if (empty($days)) {
            return $from->copy()->addWeeks($interval)->startOfDay();
        }

        sort($days);
        $current = (int) $from->dayOfWeek;
        $candidate = $from->copy()->addDay()->startOfDay();

        // Find the next matching weekday within the current week
        for ($i = 0; $i < 7; $i++) {
            if (in_array((int) $candidate->dayOfWeek, $days, true)) {
                return $candidate;
            }
            $candidate->addDay();
        }

        // Fallback: next week, same weekday as first in the pattern
        return $from->copy()->next(Carbon::getDays()[$days[0]] ?? 'Monday')->startOfDay();
    }

    private function nextMonthly(Carbon $from, array $config): Carbon
    {
        $interval = max(1, (int) ($config['interval'] ?? 1));
        $dayOfMonth = isset($config['day_of_month']) ? (int) $config['day_of_month'] : (int) $from->day;

        // Use startOfMonth before adding to prevent day-overflow (e.g. Jan 31 + 1 month → Feb 28, not Mar 3)
        $next = $from->copy()->startOfMonth()->addMonths($interval)->startOfDay();
        $next->setDay(min($dayOfMonth, $next->daysInMonth));

        return $next;
    }

    private function nextYearly(Carbon $from, array $config): Carbon
    {
        $interval = max(1, (int) ($config['interval'] ?? 1));

        return $from->copy()->addYears($interval)->startOfDay();
    }

    private function nextWeekday(Carbon $from): Carbon
    {
        $next = $from->copy()->addDay()->startOfDay();

        // Skip weekends
        while ($next->isWeekend()) {
            $next->addDay();
        }

        return $next;
    }

    private function nextCustom(Carbon $from, array $config): Carbon
    {
        $interval = max(1, (int) ($config['interval'] ?? 1));
        $unit = in_array($config['interval_unit'] ?? '', ['days', 'weeks', 'months', 'years'], true)
            ? $config['interval_unit']
            : 'days';

        return match ($unit) {
            'days' => $from->copy()->addDays($interval)->startOfDay(),
            'weeks' => $from->copy()->addWeeks($interval)->startOfDay(),
            'months' => $from->copy()->addMonths($interval)->startOfDay(),
            'years' => $from->copy()->addYears($interval)->startOfDay(),
        };
    }
}
