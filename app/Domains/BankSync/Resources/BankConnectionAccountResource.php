<?php

namespace App\Domains\BankSync\Resources;

use App\Domains\BankSync\Models\BankConnectionAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BankConnectionAccount */
class BankConnectionAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $ledger = $this->isCredit() ? $this->creditCard : $this->bankAccount;

        return [
            'id' => $this->id,
            // bank: conta corrente/poupança; credit: cartão (balance = fatura em aberto)
            'type' => $this->isCredit() ? 'credit' : 'bank',
            'subtype' => $this->subtype,
            'name' => $this->name,
            'number' => $this->number,
            'currency_code' => $this->currency_code,
            'balance_cents' => $this->balance_cents,
            'credit_limit_cents' => $this->credit_limit_cents,
            'available_credit_limit_cents' => $this->available_credit_limit_cents,
            'balance_close_date' => $this->balance_close_date?->toDateString(),
            'balance_due_date' => $this->balance_due_date?->toDateString(),
            'transactions_count' => (int) ($this->transactions_count ?? 0),
            'last_transaction_date' => $this->transactions_max_date ? substr((string) $this->transactions_max_date, 0, 10) : null,
            'transactions_synced_at' => $this->transactions_synced_at?->toIso8601String(),
            // Ledger account (bank) or card (credit) it is reconciled with
            'link' => $this->isLinked() ? [
                'bank_account_id' => $this->bank_account_id,
                'credit_card_id' => $this->credit_card_id,
                'name' => $ledger?->name,
                'import_from' => $this->import_from?->toDateString(),
            ] : null,
            'to_review_count' => (int) ($this->to_review_count ?? 0),
        ];
    }
}
