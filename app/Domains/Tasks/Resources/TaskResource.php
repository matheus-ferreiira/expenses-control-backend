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
            'due_time' => $dueDate && $this->has_due_time ? $dueDate->format('H:i') : null,
            'completed_at' => $this->completed_at?->toISOString(),
            'recurrence_type' => $this->recurrence_type?->value ?? 'none',
            'recurrence_config' => $this->recurrence_config,
            'next_occurrence_date' => $this->next_occurrence_date?->toDateString(),
            'parent_task_id' => $this->parent_task_id,
            'order' => $this->position,
            'is_archived' => $this->is_archived,
            'task_list' => $this->whenLoaded('taskList', fn () => new TaskListResource($this->taskList)),
            'tags' => TaskTagResource::collection($this->whenLoaded('tags')),
            'estimated_minutes' => $this->estimated_minutes,
            'subtasks' => SubtaskResource::collection($this->whenLoaded('subtasks')),
            'labels' => TaskLabelResource::collection($this->whenLoaded('labels')),
            'subtasks_count' => $this->subtasks_count ?? $this->whenLoaded('subtasks', fn () => $this->subtasks->count()),
            'completed_subtasks_count' => $this->whenLoaded('subtasks', fn () => $this->subtasks->where('is_completed', true)->count()),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
