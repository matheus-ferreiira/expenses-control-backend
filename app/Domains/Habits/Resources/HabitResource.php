<?php

namespace App\Domains\Habits\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HabitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'frequency_type' => $this->frequency_type?->value,
            'target_frequency' => $this->target_frequency,
            'color' => $this->color,
            'icon' => $this->icon,
            'start_date' => $this->start_date?->toDateString(),
            'is_archived' => $this->archived_at !== null,
            'archived_at' => $this->archived_at?->toISOString(),
            'logs' => HabitLogResource::collection($this->whenLoaded('logs')),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
