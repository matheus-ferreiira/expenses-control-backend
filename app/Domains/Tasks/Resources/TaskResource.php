<?php

namespace App\Domains\Tasks\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority?->value,
            'status' => $this->status?->value,
            'due_date' => $this->due_date?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'recurrence_type' => $this->recurrence_type?->value,
            'recurrence_config' => $this->recurrence_config,
            'position' => $this->position,
            'is_archived' => $this->is_archived,
            'subtasks' => SubtaskResource::collection($this->whenLoaded('subtasks')),
            'labels' => TaskLabelResource::collection($this->whenLoaded('labels')),
            'subtasks_count' => $this->subtasks_count ?? $this->whenLoaded('subtasks', fn() => $this->subtasks->count()),
            'completed_subtasks_count' => $this->whenLoaded('subtasks', fn() => $this->subtasks->where('completed', true)->count()),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
