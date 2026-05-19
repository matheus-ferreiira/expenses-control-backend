<?php

namespace App\Domains\Habits\Actions;

use App\Domains\Habits\DTOs\HabitDTO;
use App\Domains\Habits\Models\Habit;
use App\Models\User;

final class CreateHabitAction
{
    public function execute(User $user, HabitDTO $dto): Habit
    {
        return Habit::create([
            'user_id' => $user->id,
            'name' => $dto->name,
            'category' => $dto->category,
            'description' => $dto->description,
            'frequency_type' => $dto->frequencyType,
            'target_frequency' => $dto->targetFrequency,
            'target_days' => $dto->targetDays ?: null,
            'color' => $dto->color,
            'icon' => $dto->icon,
            'start_date' => $dto->startDate ?? today()->toDateString(),
        ]);
    }
}
