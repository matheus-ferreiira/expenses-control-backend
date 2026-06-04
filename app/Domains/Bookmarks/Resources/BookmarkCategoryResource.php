<?php

namespace App\Domains\Bookmarks\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookmarkCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bookmark_collection_id' => $this->bookmark_collection_id,
            'name' => $this->name,
            'position' => $this->position,
            'bookmarks_count' => $this->bookmarks_count ?? 0,
        ];
    }
}
