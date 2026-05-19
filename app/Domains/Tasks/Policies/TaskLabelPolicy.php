<?php

namespace App\Domains\Tasks\Policies;

use App\Domains\Tasks\Models\TaskLabel;
use App\Models\User;

class TaskLabelPolicy
{
    public function view(User $user, TaskLabel $label): bool
    {
        return $user->id === $label->user_id;
    }

    public function update(User $user, TaskLabel $label): bool
    {
        return $user->id === $label->user_id;
    }

    public function delete(User $user, TaskLabel $label): bool
    {
        return $user->id === $label->user_id;
    }
}
