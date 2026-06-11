<?php

namespace App\Domains\Tasks\Actions;

use App\Domains\Tasks\Enums\TaskStatus;
use App\Domains\Tasks\Models\Task;

final class CompleteTaskAction
{
    public function execute(Task $task): Task
    {
        $task->update([
            'status' => TaskStatus::Completed,
            'completed_at' => now(),
        ]);

        $task->subtasks()->update(['is_completed' => true, 'completed_at' => now()]);

        return $task->load(['labels', 'subtasks']);
    }
}
