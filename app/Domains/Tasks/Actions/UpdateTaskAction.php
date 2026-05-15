<?php

namespace App\Domains\Tasks\Actions;

use App\Domains\Tasks\DTOs\TaskDTO;
use App\Domains\Tasks\Models\Task;

final class UpdateTaskAction
{
    public function execute(Task $task, TaskDTO $dto): Task
    {
        $task->update([
            'title' => $dto->title,
            'description' => $dto->description,
            'priority' => $dto->priority,
            'status' => $dto->status,
            'due_date' => $dto->dueDate,
            'recurrence_type' => $dto->recurrenceType,
            'recurrence_config' => $dto->recurrenceConfig,
        ]);

        $task->labels()->sync($dto->labelIds ?? []);

        return $task->load(['labels', 'subtasks']);
    }
}
