<?php

namespace App\Domains\BankSync\Resources;

use App\Domains\BankSync\Models\BankConnectionTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A bank transaction waiting in "A lançar", with where it is from and what the app suggests doing.
 *
 * @property array{transaction: BankConnectionTransaction, suggestion: array<string, mixed>} $resource
 */
class BankReviewItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $b = $this->resource['transaction'];
        $account = $b->account;
        $ledger = $account->isCredit() ? $account->creditCard : $account->bankAccount;

        return [
            'id' => $b->id,
            'date' => $b->date->toDateString(),
            'description' => $b->description,
            'amount_cents' => $b->amount_cents,
            'direction' => $b->direction,
            'status' => $b->status,
            'installment_number' => $b->installment_number,
            'total_installments' => $b->total_installments,
            'account' => [
                'id' => $account->id,
                'type' => $account->isCredit() ? 'credit' : 'bank',
                'name' => $account->name,
                'bank_name' => $account->bankConnection->displayName(),
                'ledger_name' => $ledger?->name,
            ],
            // action: expense | income | transfer | pay_card | confirm; source: forecast | rule | history | pattern | null
            'suggestion' => $this->resource['suggestion'],
        ];
    }
}
