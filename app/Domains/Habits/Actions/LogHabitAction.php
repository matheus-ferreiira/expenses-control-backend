<?php

namespace App\Domains\Habits\Actions;

use App\Domains\Habits\DTOs\HabitLogDTO;
use App\Domains\Habits\Models\Habit;
use App\Domains\Habits\Models\HabitLog;

final class LogHabitAction
{
    public function execute(Habit $habit, HabitLogDTO $dto): HabitLog
    {
        return $habit->logs()->updateOrCreate(
            ['completed_date' => $dto->completedDate],
            ['notes' => $dto->notes],
        );
    }
}
