<?php

namespace App\Domains\Finance\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'card_id' => $this->card_id,
            'category_id' => $this->category_id,
            'type' => $this->type?->value,
            'amount' => (float) $this->amount,
            'description' => $this->description,
            'notes' => $this->notes,
            'transaction_date' => $this->transaction_date?->toDateString(),
            'is_recurring' => $this->is_recurring,
            'recurrence_config' => $this->recurrence_config,
            'installment_number' => $this->installment_number,
            'total_installments' => $this->total_installments,
            'installment_group_id' => $this->installment_group_id,
            'account' => new BankAccountResource($this->whenLoaded('account')),
            'card' => new CreditCardResource($this->whenLoaded('card')),
            'category' => new TransactionCategoryResource($this->whenLoaded('category')),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
