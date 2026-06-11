<?php

namespace App\Domains\Tasks\Policies;

use App\Domains\Tasks\Models\TaskList;
use App\Models\User;

class TaskListPolicy
{
    public function update(User $user, TaskList $taskList): bool
    {
        return $user->id === $taskList->user_id;
    }

    public function delete(User $user, TaskList $taskList): bool
    {
        return $user->id === $taskList->user_id;
    }
}
