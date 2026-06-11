<?php

namespace Database\Factories;

use App\Domains\Tasks\Models\Subtask;
use App\Domains\Tasks\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubtaskFactory extends Factory
{
    protected $model = Subtask::class;

    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'title' => fake()->sentence(3),
            'is_completed' => false,
            'position' => fake()->numberBetween(1, 100),
        ];
    }
}
