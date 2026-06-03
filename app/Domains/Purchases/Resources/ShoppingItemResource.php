<?php

namespace App\Domains\Purchases\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShoppingItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shopping_session_id' => $this->shopping_session_id,
            'name' => $this->name,
            'category' => $this->category,
            'is_bought' => $this->is_bought,
            'price' => $this->price,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
