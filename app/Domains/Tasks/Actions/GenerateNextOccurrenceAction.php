<?php

namespace App\Domains\Tasks\Actions;

use App\Domains\Tasks\Enums\RecurrenceType;
use App\Domains\Tasks\Enums\TaskStatus;
use App\Domains\Tasks\Models\Task;
use App\Domains\Tasks\Services\RecurrenceService;

final class GenerateNextOccurrenceAction
{
    public function __construct(
        private readonly RecurrenceService $recurrenceService,
    ) {}

    public function execute(Task $task): ?Task
    {
        if ($task->recurrence_type === RecurrenceType::None || $task->recurrence_type === null) {
            return null;
        }

        $config = $task->recurrence_config ?? [];
        $from = $task->due_date ?? now();
        $nextDate = $this->recurrenceService->calculateNextDate($task->recurrence_type, $config, $from);

        $next = Task::create([
            'user_id' => $task->user_id,
            'task_list_id' => $task->task_list_id,
            'title' => $task->title,
            'description' => $task->description,
            'priority' => $task->priority,
            'status' => TaskStatus::Pending,
            'due_date' => $nextDate,
            'recurrence_type' => $task->recurrence_type,
            'recurrence_config' => $task->recurrence_config,
            'next_occurrence_date' => $nextDate,
            'position' => $task->position,
            'estimated_minutes' => $task->estimated_minutes,
        ]);

        if ($task->labels->isNotEmpty()) {
            $next->labels()->sync($task->labels->pluck('id'));
        }

        if ($task->tags->isNotEmpty()) {
            $next->tags()->sync($task->tags->pluck('id'));
        }

        // Update the completed task to point to the new next occurrence
        $task->update(['next_occurrence_date' => $nextDate]);

        return $next;
    }
}
