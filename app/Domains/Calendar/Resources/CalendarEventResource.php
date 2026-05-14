<?php

namespace App\Domains\Calendar\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CalendarEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'description' => $this->description,
            'location' => $this->location,
            'start_date' => $this->start_date?->toISOString(),
            'end_date' => $this->end_date?->toISOString(),
            'is_all_day' => $this->is_all_day,
            'color' => $this->color,
            'source' => $this->source?->value,
            'external_id' => $this->external_id,
            'recurrence_rule' => $this->recurrence_rule,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
