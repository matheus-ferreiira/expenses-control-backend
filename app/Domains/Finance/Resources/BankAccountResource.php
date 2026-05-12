<?php

namespace App\Domains\Finance\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'bank_name' => $this->bank_name,
            'type' => $this->type?->value,
            'balance' => (float) $this->balance,
            'currency' => $this->currency,
            'color' => $this->color,
            'is_active' => $this->is_active,
            'credit_cards' => CreditCardResource::collection($this->whenLoaded('creditCards')),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
