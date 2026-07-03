<?php

namespace Database\Factories;

use App\Domains\Finance\Models\FinanceGoal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FinanceGoalFactory extends Factory
{
    protected $model = FinanceGoal::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'target_amount' => fake()->randomFloat(2, 1000, 20000),
            'monthly_contribution' => fake()->randomFloat(2, 100, 2000),
            'status' => 'active',
        ];
    }
}
