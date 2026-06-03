<?php

namespace App\Domains\Finance\Policies;

use App\Domains\Finance\Models\FinanceGoal;
use App\Models\User;

class FinanceGoalPolicy
{
    public function view(User $user, FinanceGoal $goal): bool
    {
        return $user->id === $goal->user_id;
    }

    public function update(User $user, FinanceGoal $goal): bool
    {
        return $user->id === $goal->user_id;
    }

    public function delete(User $user, FinanceGoal $goal): bool
    {
        return $user->id === $goal->user_id;
    }
}
