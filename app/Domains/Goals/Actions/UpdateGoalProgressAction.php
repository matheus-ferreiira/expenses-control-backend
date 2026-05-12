<?php

namespace App\Domains\Goals\Actions;

use App\Domains\Goals\Enums\GoalStatus;
use App\Domains\Goals\Models\Goal;

final class UpdateGoalProgressAction
{
    public function execute(Goal $goal, float $amount): Goal
    {
        $goal->current_amount = max(0, $amount);

        if ($goal->target_amount && $goal->current_amount >= $goal->target_amount) {
            $goal->status = GoalStatus::Completed;
            $goal->completed_at = now();
        }

        $goal->save();

        return $goal;
    }
}
