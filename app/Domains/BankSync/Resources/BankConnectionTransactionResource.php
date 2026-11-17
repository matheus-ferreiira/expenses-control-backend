<?php

namespace App\Domains\BankSync\Resources;

use App\Domains\BankSync\Models\BankConnectionTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BankConnectionTransaction */
class BankConnectionTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date?->toDateString(),
            'description' => $this->description,
            'amount_cents' => $this->amount_cents,
            'direction' => $this->direction,
            'status' => $this->status,
            'category' => $this->category,
            'installment_number' => $this->installment_number,
            'total_installments' => $this->total_installments,
            // null (conta não ligada), before_start, new, matched, imported, ignored
            'review_status' => $this->review_status,
            'transaction_id' => $this->transaction_id,
        ];
    }
}
