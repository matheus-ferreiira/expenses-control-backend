<?php

namespace App\Domains\Tasks\Actions;

use App\Domains\Tasks\DTOs\TaskDTO;
use App\Domains\Tasks\Models\Task;

final class UpdateTaskAction
{
    public function execute(Task $task, TaskDTO $dto): Task
    {
        $task->update([
            // Non-nullable fields: fall back to existing value when not provided
            'title' => $dto->title ?? $task->title,
            'priority' => $dto->priority ?? $task->priority,
            'status' => $dto->status ?? $task->status,
            'recurrence_type' => $dto->recurrenceType ?? $task->recurrence_type,
            // Nullable DB fields: update only when DTO was built with title present
            // (proxy for a full-form update vs. a partial status-only toggle)
            'description' => $dto->title !== null ? $dto->description : $task->description,
            'due_date' => $dto->title !== null ? $dto->dueDate : $task->due_date,
            'recurrence_config' => $dto->title !== null ? $dto->recurrenceConfig : $task->recurrence_config,
        ]);

        if ($dto->labelIds !== null) {
            $task->labels()->sync($dto->labelIds);
        }

        return $task->load(['labels', 'subtasks']);
    }
}
