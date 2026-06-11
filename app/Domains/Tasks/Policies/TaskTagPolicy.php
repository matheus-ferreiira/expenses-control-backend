<?php

namespace App\Domains\Tasks\Policies;

use App\Domains\Tasks\Models\TaskTag;
use App\Models\User;

class TaskTagPolicy
{
    public function update(User $user, TaskTag $taskTag): bool
    {
        return $user->id === $taskTag->user_id;
    }

    public function delete(User $user, TaskTag $taskTag): bool
    {
        return $user->id === $taskTag->user_id;
    }
}
