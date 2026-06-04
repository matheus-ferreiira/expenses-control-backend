<?php

namespace Database\Factories;

use App\Domains\Purchases\Models\ShoppingItem;
use App\Domains\Purchases\Models\ShoppingSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShoppingItemFactory extends Factory
{
    protected $model = ShoppingItem::class;

    public function definition(): array
    {
        return [
            'shopping_session_id' => ShoppingSession::factory(),
            'user_id' => User::factory(),
            'name' => $this->faker->words(2, true),
            'category' => null,
            'is_bought' => false,
            'price' => null,
        ];
    }

    public function bought(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_bought' => true,
        ]);
    }

    public function withPrice(): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => $this->faker->randomFloat(2, 1, 100),
        ]);
    }
}
