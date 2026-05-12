<?php

namespace App\Domains\Tasks\Actions;

use App\Domains\Tasks\DTOs\TaskDTO;
use App\Domains\Tasks\Models\Task;
use App\Models\User;

final class CreateTaskAction
{
    public function execute(User $user, TaskDTO $dto): Task
    {
        $position = $dto->position ?? Task::forUser($user->id)->max('position') + 1;

        $task = Task::create([
            'user_id' => $user->id,
            'title' => $dto->title,
            'description' => $dto->description,
            'priority' => $dto->priority,
            'status' => $dto->status,
            'due_date' => $dto->dueDate,
            'recurrence_type' => $dto->recurrenceType,
            'recurrence_config' => $dto->recurrenceConfig,
            'position' => $position,
        ]);

        if (!empty($dto->labelIds)) {
            $task->labels()->sync($dto->labelIds);
        }

        return $task->load(['labels', 'subtasks']);
    }
}
