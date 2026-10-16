<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Enums\TransactionStatus;
use App\Domains\Finance\Models\Transaction;
use App\Domains\Finance\Services\RecurrenceSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Keeps recurring series inside the rolling materialization window
 * (CreateTransactionAction::HORIZON_MONTHS):
 *
 * - EXTEND: for each active series, append pending occurrences until the horizon
 *   or the series' end condition (count/date) is reached.
 * - PRUNE: hard-delete far-future pending occurrences beyond the horizon
 *   (legacy data created before the rolling window existed).
 *
 * New occurrences continue from the LAST occurrence (including soft-deleted ones),
 * so a day changed with "this and future" sticks; a month-end date clamped from a
 * longer day (31 → 28/02) keeps the intended day. A user
 * deleting a single occurrence (soft delete) still counts toward count-based
 * series and is never recreated; pruned rows are hard-deleted so they ARE
 * recreated when the window reaches them.
 */
final class ExtendRecurringTransactionsAction
{
    public function executeForUser(string $userId): int
    {
        $horizon = Carbon::today()->addMonthsNoOverflow(CreateTransactionAction::HORIZON_MONTHS);
        $created = 0;

        $groupIds = Transaction::query()
            ->where('user_id', $userId)
            ->whereNotNull('recurrence_group_id')
            ->distinct()
            ->pluck('recurrence_group_id');

        foreach ($groupIds as $groupId) {
            $created += $this->extendGroup($groupId, $horizon);
        }

        return $created;
    }

    /** Hard-delete pending occurrences beyond the horizon (legacy pre-window data). */
    public function pruneForUser(string $userId): int
    {
        $horizon = Carbon::today()->addMonthsNoOverflow(CreateTransactionAction::HORIZON_MONTHS);

        return Transaction::query()
            ->where('user_id', $userId)
            ->whereNotNull('recurrence_group_id')
            ->where('status', TransactionStatus::Pending->value)
            ->where('transaction_date', '>', $horizon->toDateString())
            ->forceDelete();
    }

    private function extendGroup(string $groupId, Carbon $horizon): int
    {
        return (int) DB::transaction(function () use ($groupId, $horizon) {
            // Include soft-deleted rows: they anchor dates and count toward the total.
            $rows = Transaction::withTrashed()
                ->where('recurrence_group_id', $groupId)
                ->orderBy('transaction_date')
                ->get();

            // Series fully deleted by the user → nothing to extend.
            $active = $rows->whereNull('deleted_at');
            if ($active->isEmpty()) {
                return 0;
            }

            // Continue from the LAST occurrence, so a day changed with "this and future" sticks.
            $last = $rows->last();
            $template = $active->last();
            $config = (array) ($template->recurrence_config ?? []);
            $frequency = $config['frequency'] ?? 'monthly';
            $endType = $config['end_type'] ?? 'never';
            $totalTarget = $endType === 'count' ? (int) ($config['count'] ?? 0) : null;
            $endDate = $endType === 'date' && ! empty($config['end_date'])
                ? Carbon::parse($config['end_date'])->endOfDay()
                : null;

            $lastDate = $last->transaction_date->copy()->startOfDay();
            // A month-end date clamped from a longer day (31 → 28/02) keeps the intended day.
            $day = $lastDate->day;
            $previous = $rows->count() > 1 ? $rows->get($rows->count() - 2)->transaction_date : null;
            if ($previous && $lastDate->day === $lastDate->daysInMonth && $previous->day > $day) {
                $day = $previous->day;
            }
            $createdSoFar = $rows->count();
            $created = 0;
            $step = 1;

            while (true) {
                if ($totalTarget !== null && $createdSoFar >= $totalTarget) {
                    break;
                }

                $nextDate = $this->nextAfter($lastDate, $frequency, $step, $day);
                $step++;

                if ($nextDate->greaterThan($horizon)) {
                    break;
                }
                if ($endDate !== null && $nextDate->greaterThan($endDate)) {
                    break;
                }

                $occurrence = Transaction::create([
                    'user_id' => $template->user_id,
                    'account_id' => $template->account_id,
                    'destination_account_id' => $template->destination_account_id,
                    'card_id' => $template->card_id,
                    'category_id' => $template->category_id,
                    'goal_id' => $template->goal_id,
                    'type' => $template->type,
                    'amount' => $template->amount,
                    'description' => $template->description,
                    'notes' => $template->notes,
                    'transaction_date' => $nextDate->toDateString(),
                    'is_recurring' => true,
                    'recurrence_config' => $template->recurrence_config,
                    'recurrence_group_id' => $groupId,
                    'status' => TransactionStatus::Pending->value,
                ]);
                if ($tagIds = $template->tags()->pluck('transaction_tags.id')->all()) {
                    $occurrence->tags()->sync($tagIds);
                }

                $createdSoFar++;
                $created++;
            }

            return $created;
        });
    }

    /** The $step-th occurrence after $last, on $day for month-based frequencies (clamped to short months). */
    private function nextAfter(Carbon $last, string $frequency, int $step, int $day): Carbon
    {
        if (in_array($frequency, ['weekly', 'biweekly'], true)) {
            return RecurrenceSchedule::addInterval($last->copy(), $frequency, $step);
        }
        $month = RecurrenceSchedule::addInterval($last->copy()->startOfMonth(), $frequency, $step);

        return $month->setDay(min($day, $month->daysInMonth));
    }
}
