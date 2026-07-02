<?php

namespace App\Domains\Prices\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PriceSaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_id' => $this->purchase_id,
            'sale_price' => (float) $this->sale_price,
            'sold_at' => $this->sold_at->toDateString(),
            'notes' => $this->notes,
            'purchase' => new PricePurchaseResource($this->whenLoaded('purchase')),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
