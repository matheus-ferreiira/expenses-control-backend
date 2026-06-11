<?php

namespace App\Domains\Tasks\Actions;

use App\Domains\Tasks\DTOs\TaskDTO;
use App\Domains\Tasks\Models\Task;

final class UpdateTaskAction
{
    public function execute(Task $task, TaskDTO $dto): Task
    {
        $has = fn (string $key): bool => in_array($key, $dto->provided, true);

        $task->update([
            'title' => $dto->title ?? $task->title,
            'priority' => $dto->priority ?? $task->priority,
            'status' => $dto->status ?? $task->status,
            'recurrence_type' => $dto->recurrenceType ?? $task->recurrence_type,
            'description' => $has('description') ? $dto->description : $task->description,
            'due_date' => $has('due_date')
                ? ($dto->dueDate
                    ? \Carbon\Carbon::parse($dto->dueDate . ($dto->dueTime ? ' ' . $dto->dueTime : ''))
                    : null)
                : $task->due_date,
            'recurrence_config' => $has('recurrence_config') ? $dto->recurrenceConfig : $task->recurrence_config,
        ]);

        if ($dto->labelIds !== null) {
            $task->labels()->sync($dto->labelIds);
        }

        return $task->load(['labels', 'subtasks']);
    }
}
