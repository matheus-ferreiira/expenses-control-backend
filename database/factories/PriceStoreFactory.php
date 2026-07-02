<?php

namespace Database\Factories;

use App\Domains\Prices\Models\PriceStore;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PriceStoreFactory extends Factory
{
    protected $model = PriceStore::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->company(),
            'website_url' => fake()->optional()->url(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
