<?php

namespace App\Domains\Tasks\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dueDate = $this->due_date;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority?->value,
            'status' => $this->status?->value,
            'due_date' => $dueDate?->toDateString(),
            'due_time' => $dueDate?->format('H:i'),
            'completed_at' => $this->completed_at?->toISOString(),
            'is_recurring' => $this->recurrence_type?->value !== null && $this->recurrence_type?->value !== 'none',
            'recurrence_pattern' => $this->recurrence_type?->value !== 'none' ? $this->recurrence_type?->value : null,
            'order' => $this->position,
            'is_archived' => $this->is_archived,
            'subtasks' => SubtaskResource::collection($this->whenLoaded('subtasks')),
            'labels' => TaskLabelResource::collection($this->whenLoaded('labels')),
            'subtasks_count' => $this->subtasks_count ?? $this->whenLoaded('subtasks', fn () => $this->subtasks->count()),
            'completed_subtasks_count' => $this->whenLoaded('subtasks', fn () => $this->subtasks->where('completed', true)->count()),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
