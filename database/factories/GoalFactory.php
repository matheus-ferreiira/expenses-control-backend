<?php

namespace Database\Factories;

use App\Domains\Goals\Enums\GoalStatus;
use App\Domains\Goals\Enums\GoalType;
use App\Domains\Goals\Models\Goal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GoalFactory extends Factory
{
    protected $model = Goal::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => GoalType::Personal,
            'status' => GoalStatus::Active,
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->optional()->sentence(),
            'target_amount' => 1000.00,
            'current_amount' => 0.00,
            'target_date' => now()->addMonths(3)->toDateString(),
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state([
            'status' => GoalStatus::Completed,
            'current_amount' => 1000.00,
            'completed_at' => now(),
        ]);
    }
}
