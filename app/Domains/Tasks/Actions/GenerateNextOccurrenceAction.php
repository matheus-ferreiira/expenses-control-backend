<?php

namespace App\Domains\Tasks\Actions;

use App\Domains\Tasks\Enums\RecurrenceType;
use App\Domains\Tasks\Enums\TaskStatus;
use App\Domains\Tasks\Models\Task;
use App\Domains\Tasks\Services\RecurrenceService;

final class GenerateNextOccurrenceAction
{
    /** Trava de segurança para padrões que nunca alcançam hoje */
    private const MAX_CATCH_UP_STEPS = 500;

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

        // Concluir uma ocorrência atrasada não pode gerar outra já atrasada:
        // avança no padrão até alcançar hoje ou o futuro.
        $steps = 0;
        while ($nextDate->lt(today()) && $steps < self::MAX_CATCH_UP_STEPS) {
            $nextDate = $this->recurrenceService->calculateNextDate($task->recurrence_type, $config, $nextDate);
            $steps++;
        }

        $rootId = $task->parent_task_id ?? $task->id;

        // Idempotência: se a ocorrência dessa data já existe na série
        // (ex.: roll diário já materializou hoje), não duplica.
        // withTrashed: ocorrência deletada pelo usuário = dia pulado de propósito.
        $alreadyExists = Task::withTrashed()
            ->forUser($task->user_id)
            ->where(fn ($q) => $q->where('id', $rootId)->orWhere('parent_task_id', $rootId))
            ->whereDate('due_date', $nextDate)
            ->exists();

        if ($alreadyExists) {
            $task->update(['next_occurrence_date' => $nextDate]);

            return null;
        }

        $next = Task::create([
            'user_id' => $task->user_id,
            'parent_task_id' => $rootId,
            'task_list_id' => $task->task_list_id,
            'title' => $task->title,
            'description' => $task->description,
            'priority' => $task->priority,
            'status' => TaskStatus::Pending,
            'due_date' => $nextDate,
            'has_due_time' => $task->has_due_time,
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
