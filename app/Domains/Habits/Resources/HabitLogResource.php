<?php

namespace App\Domains\Habits\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HabitLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'completed_date' => $this->completed_date->toDateString(),
            'notes' => $this->notes,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
