<?php

namespace Database\Factories;

use App\Domains\Habits\Models\Habit;
use App\Domains\Habits\Models\HabitLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class HabitLogFactory extends Factory
{
    protected $model = HabitLog::class;

    public function definition(): array
    {
        return [
            'habit_id' => Habit::factory(),
            'completed_date' => today(),
            'notes' => null,
        ];
    }
}
