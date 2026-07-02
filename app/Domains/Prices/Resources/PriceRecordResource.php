<?php

namespace App\Domains\Prices\Resources;

use App\Domains\Prices\Enums\GoalStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PriceRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'store_id' => $this->store_id,
            'price' => (float) $this->price,
            'recorded_at' => $this->recorded_at->toDateString(),
            'url' => $this->url,
            'notes' => $this->notes,
            'goal_status' => $this->whenLoaded('product', fn () => GoalStatus::fromPrice(
                $this->product->target_price !== null ? (float) $this->product->target_price : null,
                (float) $this->price,
            )?->value),
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'target_price' => $this->product->target_price !== null ? (float) $this->product->target_price : null,
            ]),
            'store' => $this->whenLoaded('store', fn () => [
                'id' => $this->store->id,
                'name' => $this->store->name,
            ]),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
