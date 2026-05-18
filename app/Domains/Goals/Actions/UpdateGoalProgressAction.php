<?php

namespace App\Domains\Goals\Actions;

use App\Domains\Goals\Enums\GoalStatus;
use App\Domains\Goals\Models\Goal;
use Illuminate\Support\Facades\DB;

final class UpdateGoalProgressAction
{
    public function execute(Goal $goal, float $amount): Goal
    {
        return DB::transaction(function () use ($goal, $amount) {
            $goal->current_amount = max(0, $amount);

            if ($goal->target_amount && bccomp((string) $goal->current_amount, (string) $goal->target_amount, 2) >= 0) {
                $goal->status = GoalStatus::Completed;
                $goal->completed_at = now();
            }

            $goal->save();

            return $goal;
        });
    }
}
