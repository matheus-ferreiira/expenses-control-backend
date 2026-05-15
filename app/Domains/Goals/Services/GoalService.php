<?php

namespace App\Domains\Goals\Services;

use App\Domains\Goals\Actions\CreateGoalAction;
use App\Domains\Goals\Actions\UpdateGoalProgressAction;
use App\Domains\Goals\DTOs\GoalDTO;
use App\Domains\Goals\Models\Goal;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class GoalService
{
    public function __construct(
        private readonly CreateGoalAction $createGoal,
        private readonly UpdateGoalProgressAction $updateProgress,
    ) {}

    public function list(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Goal::forUser($user->id);

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('target_date')->paginate($filters['per_page'] ?? 15);
    }

    public function create(User $user, GoalDTO $dto): Goal
    {
        return $this->createGoal->execute($user, $dto);
    }

    public function update(Goal $goal, GoalDTO $dto): Goal
    {
        $goal->update([
            'type' => $dto->type,
            'status' => $dto->status,
            'title' => $dto->title,
            'description' => $dto->description,
            'target_amount' => $dto->targetAmount,
            'current_amount' => $dto->currentAmount,
            'target_date' => $dto->targetDate,
        ]);

        return $goal;
    }

    public function updateProgress(Goal $goal, float $amount): Goal
    {
        return $this->updateProgress->execute($goal, $amount);
    }

    public function delete(Goal $goal): void
    {
        $goal->delete();
    }
}
