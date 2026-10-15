<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Models\CreditCard;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Credit card billing cycles. A cycle is identified by the month of its DUE date ("YYYY-MM").
 * When closing_day < due_day the cycle closes in the due month (closes 13, due 20);
 * otherwise it closes in the previous month (closes 28, due 5).
 */
final class CardCycle
{
    public static function today(CreditCard $card): CarbonInterface
    {
        return Carbon::now($card->user?->timezone ?: 'America/Sao_Paulo')->startOfDay();
    }

    public static function dueDate(CreditCard $card, string $cycle): Carbon
    {
        $month = Carbon::createFromFormat('!Y-m', $cycle);

        return $month->day(min($card->due_day, $month->daysInMonth));
    }

    public static function closeDate(CreditCard $card, string $cycle): Carbon
    {
        $month = Carbon::createFromFormat('!Y-m', $cycle);
        if ($card->closing_day >= $card->due_day) {
            $month->subMonthNoOverflow();
        }

        return $month->day(min($card->closing_day, $month->daysInMonth));
    }

    public static function startDate(CreditCard $card, string $cycle): Carbon
    {
        return self::closeDate($card, self::shift($cycle, -1))->addDay();
    }

    public static function shift(string $cycle, int $months): string
    {
        return Carbon::createFromFormat('!Y-m', $cycle)->addMonthsNoOverflow($months)->format('Y-m');
    }

    /** Cycle whose purchase window (start..close) contains the given date. */
    public static function cycleFor(CreditCard $card, CarbonInterface $date): string
    {
        $day = $date->toDateString();
        $cycle = Carbon::parse($day)->format('Y-m');
        if ($card->closing_day >= $card->due_day) {
            $cycle = self::shift($cycle, 1);
        }
        while ($day > self::closeDate($card, $cycle)->toDateString()) {
            $cycle = self::shift($cycle, 1);
        }
        while ($day <= self::closeDate($card, self::shift($cycle, -1))->toDateString()) {
            $cycle = self::shift($cycle, -1);
        }

        return $cycle;
    }

    /**
     * future · open (accumulating) · closed (waiting payment) · partial · overdue · paid · empty · historical
     */
    public static function status(CreditCard $card, string $cycle, int $chargesCents, int $paidCents, bool $historical, CarbonInterface $today): string
    {
        $day = $today->toDateString();
        if ($historical) {
            return 'historical';
        }
        if ($day < self::startDate($card, $cycle)->toDateString()) {
            return 'future';
        }
        if ($day <= self::closeDate($card, $cycle)->toDateString()) {
            return 'open';
        }
        if ($chargesCents <= 0) {
            return 'empty';
        }
        if ($paidCents >= $chargesCents) {
            return 'paid';
        }
        if ($day > self::dueDate($card, $cycle)->toDateString()) {
            return 'overdue';
        }

        return $paidCents > 0 ? 'partial' : 'closed';
    }
}
