<?php

namespace App\Domains\Finance\Services;

use Carbon\Carbon;

/** Recurrence rules shared by creation, edition and the rolling extension (they used to be copied in 3 Actions). */
final class RecurrenceSchedule
{
    /** Max occurrences when end_type=never, keyed by frequency (5 years). */
    public const MAX_BY_FREQUENCY = [
        'weekly' => 260,
        'biweekly' => 130,
        'monthly' => 60,
        'bimonthly' => 30,
        'quarterly' => 20,
        'semiannual' => 10,
        'annual' => 5,
    ];

    /**
     * Advance $date by $step intervals of $frequency.
     * $step is the 0-based occurrence index (0 = base date, 1 = first advance, …).
     */
    public static function addInterval(Carbon $date, string $frequency, int $step): Carbon
    {
        return match ($frequency) {
            'weekly' => $date->addWeeks($step),
            'biweekly' => $date->addWeeks($step * 2),
            'bimonthly' => $date->addMonthsNoOverflow($step * 2),
            'quarterly' => $date->addMonthsNoOverflow($step * 3),
            'semiannual' => $date->addMonthsNoOverflow($step * 6),
            'annual' => $date->addYearsNoOverflow($step),
            default => $date->addMonthsNoOverflow($step),  // 'monthly' + fallback
        };
    }

    /** Count how many occurrences fit from $baseDate up to $endDateStr (inclusive). */
    public static function countUntilDate(Carbon $baseDate, string $frequency, ?string $endDateStr, int $max): int
    {
        if (! $endDateStr) {
            return $max;
        }

        $endDate = Carbon::parse($endDateStr)->endOfDay();
        $count = 0;

        for ($i = 0; $i < $max; $i++) {
            if (self::addInterval($baseDate->copy(), $frequency, $i)->greaterThan($endDate)) {
                break;
            }
            $count++;
        }

        return max(1, $count);
    }
}
