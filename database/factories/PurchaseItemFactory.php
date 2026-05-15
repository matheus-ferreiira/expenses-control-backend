<?php

namespace Database\Factories;

use App\Domains\Purchases\Models\PurchaseItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseItemFactory extends Factory
{
    protected $model = PurchaseItem::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->words(3, true),
            'category' => $this->faker->randomElement(['Eletrônicos', 'Roupas', 'Alimentação', 'Casa', null]),
            'is_bought' => false,
        ];
    }

    public function bought(): static
    {
        return $this->state(['is_bought' => true]);
    }
}
