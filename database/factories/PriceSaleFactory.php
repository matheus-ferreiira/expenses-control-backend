<?php

namespace Database\Factories;

use App\Domains\Prices\Models\PricePurchase;
use App\Domains\Prices\Models\PriceSale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PriceSaleFactory extends Factory
{
    protected $model = PriceSale::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'purchase_id' => PricePurchase::factory(),
            'sale_price' => fake()->randomFloat(2, 50, 8000),
            'sold_at' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
