<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\DTOs\TransactionDTO;
use App\Domains\Finance\Enums\TransactionType;
use App\Domains\Finance\Models\CreditCard;
use App\Domains\Finance\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
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
    public function execute(User $user, CreditCard $card, string $accountId, float $amount, string $statementMonth, ?string $paymentDate = null): Transaction
    {
        $alreadyPaid = Transaction::query()
            ->where('card_id', $card->id)
            ->where('statement_month', $statementMonth)
            ->where('type', TransactionType::Transfer->value)
            ->exists();

        if ($alreadyPaid) {
            throw ValidationException::withMessages([
                'statement_month' => ['Esta fatura já foi paga.'],
            ]);
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

        return $this->createTransaction->execute($user, $dto);
    }
}
