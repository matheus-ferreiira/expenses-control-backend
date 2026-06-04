<?php

namespace App\Domains\Bookmarks\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookmarkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $host = parse_url($this->url, PHP_URL_HOST);
        $faviconUrl = $host
            ? "https://www.google.com/s2/favicons?domain={$host}&sz=32"
            : null;

        return [
            'id' => $this->id,
            'bookmark_category_id' => $this->bookmark_category_id,
            'title' => $this->title,
            'url' => $this->url,
            'description' => $this->description,
            'is_favorite' => $this->is_favorite,
            'position' => $this->position,
            'favicon_url' => $faviconUrl,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
