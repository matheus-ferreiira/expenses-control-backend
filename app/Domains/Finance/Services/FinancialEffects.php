<?php

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Enums\TransactionType;
use App\Domains\Finance\Models\Transaction;
use InvalidArgumentException;

/** Amounts stay in integer cents until the API presentation boundary. */
final class FinancialEffects
{
    public static function cents(string $amount): int
    {
        if (! preg_match('/^(-?)(\d{1,13})(?:\.(\d{1,2}))?$/D', $amount, $parts)) {
            throw new InvalidArgumentException('Invalid monetary amount');
        }

        return ($parts[1] === '-' ? -1 : 1) * ((int) $parts[2] * 100 + (int) str_pad($parts[3] ?? '', 2, '0'));
    }

    public static function decimal(int $cents): string
    {
        return ($cents < 0 ? '-' : '').intdiv(abs($cents), 100).'.'.str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Economic fields for an income marked (or unmarked) as a reimbursement: it gives back money
     * already spent, so it is not income. Unmarking falls back to the plain amount.
     */
    public static function reimbursementFields(Transaction $transaction, bool $isReimbursement): array
    {
        if ($isReimbursement && $transaction->type === TransactionType::Income) {
            return ['economic_kind' => 'reimbursement', 'classification_status' => 'identified', 'economic_income_cents' => 0, 'economic_expense_cents' => 0];
        }
        if (! $isReimbursement && $transaction->economic_kind === 'reimbursement') {
            return ['economic_kind' => null, 'economic_income_cents' => null, 'economic_expense_cents' => null];
        }

        return [];
    }

    public function economic(Transaction $transaction): array
    {
        if ($transaction->reconciliation_status === 'conflict' || $transaction->goal_id) {
            return ['income' => 0, 'expense' => 0];
        }
        if ($transaction->economic_income_cents !== null && $transaction->economic_expense_cents !== null) {
            return ['income' => (int) $transaction->economic_income_cents, 'expense' => (int) $transaction->economic_expense_cents];
        }
        if (in_array($transaction->economic_kind, ['unknown', 'reimbursement', 'internal_transfer', 'card_payment', 'historical_debt', 'informational'], true)) {
            return ['income' => 0, 'expense' => 0];
        }
        $cents = self::cents($transaction->amount);

        return [
            'income' => $transaction->type === TransactionType::Income ? $cents : 0,
            'expense' => $transaction->type === TransactionType::Expense ? $cents : 0,
        ];
    }
}
