<?php

namespace Database\Factories;

use App\Domains\Tasks\Enums\TaskPriority;
use App\Domains\Tasks\Enums\TaskStatus;
use App\Domains\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->sentence(),
            'priority' => TaskPriority::Normal,
            'status' => TaskStatus::Pending,
            'is_archived' => false,
            'position' => fake()->numberBetween(1, 1000),
        ];
    }

    public function archived(): static
    {
        return $this->state(['is_archived' => true]);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => TaskStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}
