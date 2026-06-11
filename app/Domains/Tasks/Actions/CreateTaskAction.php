<?php

namespace App\Domains\Tasks\Actions;

use App\Domains\Tasks\DTOs\TaskDTO;
use App\Domains\Tasks\Enums\RecurrenceType;
use App\Domains\Tasks\Enums\TaskPriority;
use App\Domains\Tasks\Enums\TaskStatus;
use App\Domains\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreateTaskAction
{
    public function execute(User $user, TaskDTO $dto): Task
    {
        $task = DB::transaction(function () use ($user, $dto) {
            $position = $dto->position
                ?? (Task::forUser($user->id)->lockForUpdate()->max('position') ?? 0) + 1;

            $task = Task::create([
                'user_id' => $user->id,
                'title' => $dto->title,
                'description' => $dto->description,
                'priority' => $dto->priority ?? TaskPriority::Normal,
                'status' => $dto->status ?? TaskStatus::Pending,
                'due_date' => $dto->dueDate
                    ? \Carbon\Carbon::parse($dto->dueDate . ($dto->dueTime ? ' ' . $dto->dueTime : ''))
                    : null,
                'recurrence_type' => $dto->recurrenceType ?? RecurrenceType::None,
                'recurrence_config' => $dto->recurrenceConfig,
                'position' => $position,
            ]);

            if (! empty($dto->labelIds)) {
                $task->labels()->sync($dto->labelIds);
            }

            return $task;
        });

        return $task->load(['labels', 'subtasks']);
    }
}
