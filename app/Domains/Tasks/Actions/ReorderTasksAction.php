<?php

namespace App\Domains\Tasks\Actions;

use App\Domains\Tasks\Models\Task;
use App\Models\User;

final class ReorderTasksAction
{
    public function execute(User $user, array $orderedIds): void
    {
        foreach ($orderedIds as $position => $taskId) {
            Task::forUser($user->id)
                ->where('id', $taskId)
                ->update(['position' => $position + 1]);
        }
    }
}
