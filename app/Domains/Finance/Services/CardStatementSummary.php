<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Models\CreditCard;
use App\Domains\Finance\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class CardStatementSummary
{
    public function get(CreditCard $card, string $cycle): array
    {
        $due = Carbon::createFromFormat('!Y-m', $cycle)->day(min($card->due_day, Carbon::createFromFormat('!Y-m', $cycle)->daysInMonth));
        $close = $due->copy();
        if ($card->closing_day >= $card->due_day) {
            $close->subMonthNoOverflow();
        }
        $close->day(min($card->closing_day, $close->daysInMonth));
        $start = $close->copy()->subMonthNoOverflow()->addDay()->toDateString();
        $rows = Transaction::forUser($card->user_id)->where('card_id', $card->id)->where(function ($q) use ($cycle, $start, $close) {
            $q->where('statement_month', $cycle)->orWhere(fn ($legacy) => $legacy->whereNull('statement_month')->whereNull('import_batch_id')->where('type', '!=', 'transfer')->whereBetween('transaction_date', [$start, $close->toDateString()]));
        })->with(['account', 'category', 'tags'])->orderBy('transaction_date')->get();
        $payments = $rows->filter(fn ($t) => $t->type->value === 'transfer');
        $charges = $rows->reject(fn ($t) => $t->type->value === 'transfer');
        $total = $charges->sum(fn ($t) => $t->signed_card_cents !== null ? (int) $t->signed_card_cents : FinancialEffects::cents($t->amount) * ($t->type->value === 'income' ? -1 : 1));
        $paid = $payments->filter(fn ($t) => $t->isConfirmed())->sum(fn ($t) => FinancialEffects::cents($t->amount));
        $evidence = DB::table('card_statements')->where('user_id', $card->user_id)->where('card_id', $card->id)->where('cycle', $cycle)->first();

        return ['cycle' => $cycle, 'transactions' => $charges->values(), 'payments' => $payments->values(), 'charges_cents' => $total, 'paid_cents' => $paid, 'remaining_cents' => max(0, $total - $paid), 'documentary_cents' => $evidence?->documentary_cents, 'manual_cents' => $evidence?->manual_cents, 'difference_cents' => $evidence && $evidence->manual_cents !== null ? $evidence->documentary_cents - $evidence->manual_cents : null];
    }
}
