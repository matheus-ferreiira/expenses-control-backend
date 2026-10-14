<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\DTOs\TransactionDTO;
use App\Domains\Finance\Enums\TransactionType;
use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\CreditCard;
use App\Domains\Finance\Models\Transaction;
use App\Domains\Finance\Services\FinancialEffects;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PayCardStatementAction
{
    public function __construct(
        private readonly CreateTransactionAction $createTransaction,
    ) {}

    /**
     * Settle a credit card statement: a transfer from the chosen bank account to the
     * card, tagged with the statement month. Transfers stay out of income/expense
     * reports, so the purchase (counted when it happened) is never double-counted.
     */
    public function execute(User $user, CreditCard $card, string $accountId, float $amount, string $statementMonth, ?string $paymentDate = null, ?string $paymentReference = null): Transaction
    {
        return DB::transaction(function () use ($user, $card, $accountId, $amount, $statementMonth, $paymentDate, $paymentReference) {
            CreditCard::forUser($user->id)->whereKey($card->id)->lockForUpdate()->firstOrFail();
            BankAccount::forUser($user->id)->whereKey($accountId)->lockForUpdate()->firstOrFail();
            $date = $paymentDate ?? Carbon::today()->toDateString();
            $reference = $paymentReference ?? hash('sha256', implode('|', [$card->id, $accountId, number_format($amount, 2, '.', ''), $statementMonth, $date]));
            $existing = Transaction::withTrashed()->forUser($user->id)->where('payment_reference', $reference)->first();
            if ($existing) {
                if ($paymentReference === null || $existing->deleted_at || $existing->card_id !== $card->id || $existing->account_id !== $accountId || $existing->statement_month !== $statementMonth || $existing->transaction_date->toDateString() !== $date || FinancialEffects::cents($existing->amount) !== FinancialEffects::cents(number_format($amount, 2, '.', ''))) {
                    throw ValidationException::withMessages(['payment_reference' => ['Referência de pagamento já utilizada.']]);
                }

                return $existing;
            }

            $monthLabel = Carbon::createFromFormat('Y-m', $statementMonth)
                ->locale('pt_BR')
                ->translatedFormat('M/Y');

            $dto = new TransactionDTO(
                type: TransactionType::Transfer,
                amount: $amount,
                description: "Fatura {$card->name} · {$monthLabel}",
                transactionDate: $paymentDate ?? Carbon::today()->toDateString(),
                accountId: $accountId,
                cardId: $card->id,
                statementMonth: $statementMonth,
            );

            $transaction = $this->createTransaction->execute($user, $dto);
            $transaction->forceFill(['payment_reference' => $reference, 'economic_kind' => 'card_payment'])->save();

            return $transaction;
        });
    }
}
