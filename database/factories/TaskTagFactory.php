<?php

namespace Database\Factories;

use App\Domains\Tasks\Models\TaskTag;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskTagFactory extends Factory
{
    protected $model = TaskTag::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->word(),
            'color' => fake()->hexColor(),
        ];
    }
}
