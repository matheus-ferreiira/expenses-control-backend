<?php

namespace App\Domains\Goals\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type?->value,
            'status' => $this->status?->value,
            'title' => $this->title,
            'description' => $this->description,
            'target_amount' => $this->target_amount !== null ? round((float) $this->target_amount, 2) : null,
            'current_amount' => round((float) $this->current_amount, 2),
            'progress_percentage' => $this->progress_percentage,
            'target_date' => $this->target_date?->toDateString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'is_overdue' => $this->target_date && $this->target_date->isPast() && $this->status?->value === 'active',
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
