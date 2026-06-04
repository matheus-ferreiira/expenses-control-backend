<?php

namespace App\Domains\Bookmarks\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookmarkCollectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'icon' => $this->icon,
            'color' => $this->color,
            'position' => $this->position,
            'bookmarks_count' => $this->bookmarks_count ?? 0,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
