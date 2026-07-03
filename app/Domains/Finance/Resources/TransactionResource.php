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
            'destination_account_id' => $this->destination_account_id,
            'card_id' => $this->card_id,
            'statement_month' => $this->statement_month,
            'category_id' => $this->category_id,
            'goal_id' => $this->goal_id,
            'type' => $this->type?->value,
            'amount' => (float) $this->amount,
            'description' => $this->description,
            'notes' => $this->notes,
            'transaction_date' => $this->transaction_date?->toDateString(),
            'is_recurring' => $this->is_recurring,
            'recurrence_config' => $this->recurrence_config,
            'status' => $this->status?->value,
            'recurrence_group_id' => $this->recurrence_group_id,
            'installment_number' => $this->installment_number,
            'total_installments' => $this->total_installments,
            'installment_group_id' => $this->installment_group_id,
            'account' => new BankAccountResource($this->whenLoaded('account')),
            'destination_account' => new BankAccountResource($this->whenLoaded('destinationAccount')),
            'card' => new CreditCardResource($this->whenLoaded('card')),
            'category' => new TransactionCategoryResource($this->whenLoaded('category')),
            'tags' => TransactionTagResource::collection($this->whenLoaded('tags')),
            'goal' => $this->whenLoaded('goal', fn () => [
                'id' => $this->goal->id,
                'name' => $this->goal->name,
                'color' => $this->goal->color,
                'icon' => $this->goal->icon,
            ]),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
