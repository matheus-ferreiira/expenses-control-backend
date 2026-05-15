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

        $task->subtasks()->update(['completed' => true]);

        return $task;
    }
}
