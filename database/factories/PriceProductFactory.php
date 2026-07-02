<?php

namespace Database\Factories;

use App\Domains\Prices\Enums\ProductStatus;
use App\Domains\Prices\Models\PriceProduct;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PriceProductFactory extends Factory
{
    protected $model = PriceProduct::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => null,
            'name' => fake()->words(3, true),
            'brand' => fake()->optional()->company(),
            'model' => fake()->optional()->bothify('??-####'),
            'specs' => fake()->optional()->sentence(),
            'notes' => fake()->optional()->sentence(),
            'target_price' => fake()->optional()->randomFloat(2, 500, 5000),
            'launch_price' => fake()->optional()->randomFloat(2, 1000, 10000),
            'status' => ProductStatus::Tracking,
        ];
    }

    public function purchased(): static
    {
        return $this->state(fn () => ['status' => ProductStatus::Purchased]);
    }

    public function discarded(): static
    {
        return $this->state(fn () => ['status' => ProductStatus::Discarded]);
    }
}
