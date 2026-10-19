<?php

namespace App\Domains\Finance\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

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
            // Reserve accounts hold money put away: shown as "Guardado", outside the available balance
            'is_reserve' => $this->isReserve(),
            // Last balance check and whether the account has a verified starting point (D-17, D-42)
            'last_checked_on' => DB::table('account_balance_checkpoints')->where('account_id', $this->id)->max('observed_on'),
            'balance_verified' => DB::table('account_balance_checkpoints')->where('account_id', $this->id)->where('kind', 'opening')->exists(),
            'credit_cards' => CreditCardResource::collection($this->whenLoaded('creditCards')),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
