<?php

namespace App\Domains\Goals\Actions;

use App\Domains\Goals\DTOs\GoalDTO;
use App\Domains\Goals\Models\Goal;
use App\Models\User;

final class CreateGoalAction
{
    public function execute(User $user, GoalDTO $dto): Goal
    {
        return Goal::create([
            'user_id' => $user->id,
            'type' => $dto->type,
            'status' => $dto->status,
            'title' => $dto->title,
            'description' => $dto->description,
            'target_amount' => $dto->targetAmount,
            'current_amount' => $dto->currentAmount,
            'target_date' => $dto->targetDate,
        ]);
    }
}
