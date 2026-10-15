<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Models\CreditCard;
use App\Domains\Finance\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class CardStatementSummary
{
    public function get(CreditCard $card, string $cycle, ?CarbonInterface $today = null): array
    {
        $today ??= CardCycle::today($card);
        $start = CardCycle::startDate($card, $cycle)->toDateString();
        $close = CardCycle::closeDate($card, $cycle)->toDateString();
        $due = CardCycle::dueDate($card, $cycle)->toDateString();
        $rows = Transaction::forUser($card->user_id)->where('card_id', $card->id)->where(function ($q) use ($cycle, $start, $close) {
            $q->where('statement_month', $cycle)->orWhere(fn ($legacy) => $legacy->whereNull('statement_month')->whereNull('import_batch_id')->where('type', '!=', 'transfer')->whereBetween('transaction_date', [$start, $close]));
        })->with(['account', 'category', 'tags'])->orderBy('transaction_date')->get();
        $payments = $rows->filter(fn ($t) => $t->type->value === 'transfer');
        $charges = $rows->reject(fn ($t) => $t->type->value === 'transfer');
        $total = $charges->sum(fn ($t) => self::signedCents($t));
        $paid = $payments->filter(fn ($t) => $t->isConfirmed())->sum(fn ($t) => FinancialEffects::cents($t->amount));
        $evidence = DB::table('card_statements')->where('user_id', $card->user_id)->where('card_id', $card->id)->where('cycle', $cycle)->first();
        $historical = $evidence?->status === 'historico_quitado';

        return [
            'cycle' => $cycle, 'start_on' => $start, 'closing_on' => $close, 'due_on' => $due,
            'status' => CardCycle::status($card, $cycle, $total, $paid, $historical, $today),
            'historical' => $historical,
            'transactions' => $charges->values(), 'payments' => $payments->values(),
            'charges_cents' => $total, 'paid_cents' => $paid,
            // Historical statements were settled during the 2026 renegotiation; nothing is left to pay on them.
            'remaining_cents' => $historical ? 0 : max(0, $total - $paid),
            'documentary_cents' => $evidence?->documentary_cents, 'manual_cents' => $evidence?->manual_cents,
            'difference_cents' => $evidence && $evidence->manual_cents !== null ? $evidence->documentary_cents - $evidence->manual_cents : null,
        ];
    }

    /** Purchases and fees add to the statement; refunds (income on the card) reduce it. */
    public static function signedCents(Transaction $transaction): int
    {
        return $transaction->signed_card_cents !== null
            ? (int) $transaction->signed_card_cents
            : FinancialEffects::cents($transaction->amount) * ($transaction->type->value === 'income' ? -1 : 1);
    }
}
