<?php

namespace Database\Factories;

use App\Domains\Purchases\Models\ShoppingSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShoppingSessionFactory extends Factory
{
    protected $model = ShoppingSession::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->words(3, true),
            'status' => 'active',
            'total' => null,
            'finished_at' => null,
            'transaction_id' => null,
        ];
    }

    public function finished(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'finished',
            'total' => $this->faker->randomFloat(2, 10, 500),
            'finished_at' => now(),
        ]);
    }
}
