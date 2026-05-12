<?php

namespace App\Domains\Habits\Actions;

use App\Domains\Habits\DTOs\HabitLogDTO;
use App\Domains\Habits\Models\Habit;
use App\Domains\Habits\Models\HabitLog;

final class LogHabitAction
{
    public function execute(Habit $habit, HabitLogDTO $dto): HabitLog
    {
        $existing = $habit->logs()->whereDate('completed_date', $dto->completedDate)->first();

        if ($existing) {
            $existing->update(['notes' => $dto->notes]);
            return $existing;
        }

        return $habit->logs()->create([
            'completed_date' => $dto->completedDate,
            'notes' => $dto->notes,
        ]);
    }
}
