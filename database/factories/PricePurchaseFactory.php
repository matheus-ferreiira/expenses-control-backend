<?php

namespace Database\Factories;

use App\Domains\Prices\Models\PriceProduct;
use App\Domains\Prices\Models\PricePurchase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PricePurchaseFactory extends Factory
{
    protected $model = PricePurchase::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'product_id' => PriceProduct::factory(),
            'store_id' => null,
            'price_paid' => fake()->randomFloat(2, 100, 10000),
            'purchased_at' => fake()->dateTimeBetween('-3 years')->format('Y-m-d'),
            'warranty_months' => fake()->optional()->randomElement([12, 24, 36]),
            'current_value' => fake()->optional()->randomFloat(2, 50, 8000),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
