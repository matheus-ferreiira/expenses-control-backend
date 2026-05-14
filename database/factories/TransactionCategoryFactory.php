<?php

namespace Database\Factories;

use App\Domains\Finance\Enums\TransactionType;
use App\Domains\Finance\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionCategoryFactory extends Factory
{
    protected $model = TransactionCategory::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->word(),
            'type' => TransactionType::Expense,
            'icon' => 'tag',
            'color' => '#6366f1',
            'is_default' => false,
        ];
    }
}
