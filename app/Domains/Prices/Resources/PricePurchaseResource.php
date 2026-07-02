<?php

namespace App\Domains\Prices\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PricePurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'store_id' => $this->store_id,
            'price_paid' => (float) $this->price_paid,
            'purchased_at' => $this->purchased_at->toDateString(),
            'warranty_months' => $this->warranty_months,
            'current_value' => $this->current_value !== null ? (float) $this->current_value : null,
            'notes' => $this->notes,
            'is_sold' => $this->when($this->resource->relationLoaded('sale'), fn () => $this->sale !== null),
            'sale' => $this->when($this->resource->relationLoaded('sale'), fn () => $this->sale !== null ? (new PriceSaleResource($this->sale))->resolve() : null),
            'product' => new PriceProductResource($this->whenLoaded('product')),
            'store' => new PriceStoreResource($this->whenLoaded('store')),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
