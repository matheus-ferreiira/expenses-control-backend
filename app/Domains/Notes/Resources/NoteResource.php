<?php

namespace App\Domains\Notes\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'content' => $this->content,
            'is_pinned' => $this->is_pinned,
            'is_favorite' => $this->is_favorite,
            'is_archived' => ! is_null($this->archived_at),
            'archived_at' => $this->archived_at?->toISOString(),
            'last_viewed_at' => $this->last_viewed_at?->toISOString(),
            'tags' => NoteTagResource::collection($this->whenLoaded('tags')),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
