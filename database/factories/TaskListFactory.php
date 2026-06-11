<?php

namespace Database\Factories;

use App\Domains\Tasks\Models\TaskList;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskListFactory extends Factory
{
    protected $model = TaskList::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'color' => fake()->hexColor(),
            'icon' => null,
            'position' => fake()->numberBetween(1, 100),
            'is_default' => false,
        ];
    }
}
