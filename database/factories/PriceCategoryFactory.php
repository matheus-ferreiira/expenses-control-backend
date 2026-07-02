<?php

namespace Database\Factories;

use App\Domains\Prices\Models\PriceCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PriceCategoryFactory extends Factory
{
    protected $model = PriceCategory::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            'icon' => fake()->optional()->randomElement(['cpu', 'monitor', 'keyboard', 'mouse', 'hard-drive']),
        ];
    }
}
