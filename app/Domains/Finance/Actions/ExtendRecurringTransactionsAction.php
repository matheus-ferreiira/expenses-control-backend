<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Enums\TransactionStatus;
use App\Domains\Finance\Models\Transaction;
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
 * Occurrence dates are anchored on the FIRST occurrence ever created (including
 * soft-deleted ones), so day-of-month is preserved across short months. A user
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

            $anchor = $rows->first();
            $template = $active->last();
            $config = (array) ($template->recurrence_config ?? []);
            $frequency = $config['frequency'] ?? 'monthly';
            $endType = $config['end_type'] ?? 'never';
            $totalTarget = $endType === 'count' ? (int) ($config['count'] ?? 0) : null;
            $endDate = $endType === 'date' && ! empty($config['end_date'])
                ? Carbon::parse($config['end_date'])->endOfDay()
                : null;

            $anchorDate = $anchor->transaction_date->copy()->startOfDay();
            $createdSoFar = $rows->count();
            $created = 0;

            while (true) {
                if ($totalTarget !== null && $createdSoFar >= $totalTarget) {
                    break;
                }

                $nextDate = $this->addInterval($anchorDate->copy(), $frequency, $createdSoFar);

                if ($nextDate->greaterThan($horizon)) {
                    break;
                }
                if ($endDate !== null && $nextDate->greaterThan($endDate)) {
                    break;
                }

                Transaction::create([
                    'user_id' => $template->user_id,
                    'account_id' => $template->account_id,
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

                $createdSoFar++;
                $created++;
            }

            return $created;
        });
    }

    private function addInterval(Carbon $date, string $frequency, int $step): Carbon
    {
        return match ($frequency) {
            'weekly' => $date->addWeeks($step),
            'biweekly' => $date->addWeeks($step * 2),
            'bimonthly' => $date->addMonthsNoOverflow($step * 2),
            'quarterly' => $date->addMonthsNoOverflow($step * 3),
            'semiannual' => $date->addMonthsNoOverflow($step * 6),
            'annual' => $date->addYearsNoOverflow($step),
            default => $date->addMonthsNoOverflow($step),
        };
    }
}
