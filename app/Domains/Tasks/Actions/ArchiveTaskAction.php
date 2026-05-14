<?php

namespace App\Domains\Tasks\Actions;

use App\Domains\Tasks\Models\Task;

final class ArchiveTaskAction
{
    public function execute(Task $task, bool $archive = true): Task
    {
        $task->update(['is_archived' => $archive]);

        return $task;
    }
}
