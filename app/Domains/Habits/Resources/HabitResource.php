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
            'user_id' => $this->user_id,
            'name' => $this->name,
            'description' => $this->description,
            // 'frequency' maps to frontend Habit.frequency
            'frequency' => $this->frequency_type?->value,
            'target_days' => [],
            'color' => $this->color,
            'icon' => $this->icon,
            'is_active' => $this->archived_at === null,
            'is_archived' => $this->archived_at !== null,
            'current_streak' => 0,
            'longest_streak' => 0,
            'logs' => HabitLogResource::collection($this->whenLoaded('logs')),
            'start_date' => $this->start_date?->toDateString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
