<?php

namespace App\Domains\Habits\Policies;

use App\Domains\Habits\Models\Habit;
use App\Models\User;

class HabitPolicy
{
    public function view(User $user, Habit $habit): bool
    {
        return $user->id === $habit->user_id;
    }

    public function update(User $user, Habit $habit): bool
    {
        return $user->id === $habit->user_id;
    }

    public function delete(User $user, Habit $habit): bool
    {
        return $user->id === $habit->user_id;
    }
}
