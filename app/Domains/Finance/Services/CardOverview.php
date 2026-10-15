<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Enums\TransactionType;
use App\Domains\Finance\Models\CreditCard;
use App\Domains\Finance\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Everything the card screen needs in one place, computed with the same cycle rules as
 * CardStatementSummary: statements waiting for payment, the open statement, the amounts
 * already committed to the next statements and the real available limit.
 */
final class CardOverview
{
    private const UPCOMING_LIMIT = 6;

    public function get(CreditCard $card, ?CarbonInterface $today = null): array
    {
        $today ??= CardCycle::today($card);
        $openCycle = CardCycle::cycleFor($card, $today);

        $charges = [];
        $paid = [];
        $rows = Transaction::forUser($card->user_id)->where('card_id', $card->id)
            ->get(['type', 'amount', 'signed_card_cents', 'statement_month', 'import_batch_id', 'transaction_date', 'status']);
        foreach ($rows as $transaction) {
            if ($transaction->type === TransactionType::Transfer) {
                if ($transaction->statement_month && $transaction->isConfirmed()) {
                    $paid[$transaction->statement_month] = ($paid[$transaction->statement_month] ?? 0) + FinancialEffects::cents($transaction->amount);
                }

                continue;
            }
            $cycle = $transaction->statement_month
                ?? ($transaction->import_batch_id === null ? CardCycle::cycleFor($card, $transaction->transaction_date) : null);
            if ($cycle !== null) {
                $charges[$cycle] = ($charges[$cycle] ?? 0) + CardStatementSummary::signedCents($transaction);
            }
        }
        $historical = DB::table('card_statements')->where('user_id', $card->user_id)->where('card_id', $card->id)
            ->where('status', 'historico_quitado')->pluck('cycle')->flip()->all();

        $cycles = array_unique([...array_keys($charges), ...array_keys($paid), $openCycle]);
        sort($cycles);
        $statements = [];
        $usedCents = 0;
        foreach ($cycles as $cycle) {
            $isHistorical = isset($historical[$cycle]);
            $c = $charges[$cycle] ?? 0;
            $p = $paid[$cycle] ?? 0;
            $remaining = $isHistorical ? 0 : max(0, $c - $p);
            $usedCents += $remaining;
            $statements[$cycle] = [
                'cycle' => $cycle,
                'start_on' => CardCycle::startDate($card, $cycle)->toDateString(),
                'closing_on' => CardCycle::closeDate($card, $cycle)->toDateString(),
                'due_on' => CardCycle::dueDate($card, $cycle)->toDateString(),
                'status' => CardCycle::status($card, $cycle, $c, $p, $isHistorical, $today),
                'charges_cents' => $c,
                'paid_cents' => $p,
                'remaining_cents' => $remaining,
            ];
        }

        $toPay = array_values(array_filter($statements, fn ($s) => in_array($s['status'], ['closed', 'partial', 'overdue'], true)));
        $upcoming = array_values(array_filter($statements, fn ($s) => $s['cycle'] > $openCycle && $s['charges_cents'] > 0));
        $limitCents = FinancialEffects::cents($card->limit_amount);

        return [
            'card_id' => $card->id,
            'today' => $today->toDateString(),
            'open_cycle' => $openCycle,
            'to_pay' => $toPay,
            'open' => $statements[$openCycle],
            'upcoming' => array_slice($upcoming, 0, self::UPCOMING_LIMIT),
            'upcoming_total_cents' => array_sum(array_column($upcoming, 'remaining_cents')),
            'limit_cents' => $limitCents,
            'used_cents' => $usedCents,
            'available_cents' => $limitCents - $usedCents,
        ];
    }
}
