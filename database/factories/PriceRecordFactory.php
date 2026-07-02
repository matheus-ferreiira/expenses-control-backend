<?php

namespace Database\Factories;

use App\Domains\Prices\Models\PriceProduct;
use App\Domains\Prices\Models\PriceRecord;
use App\Domains\Prices\Models\PriceStore;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PriceRecordFactory extends Factory
{
    protected $model = PriceRecord::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'product_id' => PriceProduct::factory(),
            'store_id' => PriceStore::factory(),
            'price' => fake()->randomFloat(2, 100, 10000),
            'recorded_at' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
            'url' => fake()->optional()->url(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
