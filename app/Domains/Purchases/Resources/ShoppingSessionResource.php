<?php

namespace App\Domains\Purchases\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShoppingSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $itemsLoaded = $this->relationLoaded('items');
        $items = $itemsLoaded ? $this->items : collect();

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'status' => $this->status,
            'total' => $this->total,
            'finished_at' => $this->finished_at?->toISOString(),
            'transaction_id' => $this->transaction_id,
            'items' => $this->whenLoaded('items', fn () => ShoppingItemResource::collection($this->items)),
            'items_count' => $items->count(),
            'bought_count' => $items->where('is_bought', true)->count(),
            'suggested_total' => (float) $items->where('is_bought', true)->whereNotNull('price')->sum('price'),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
