<?php

namespace Database\Factories;

use App\Domains\Tasks\Models\TaskLabel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskLabelFactory extends Factory
{
    protected $model = TaskLabel::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->word(),
            'color' => fake()->hexColor(),
        ];
    }
}
