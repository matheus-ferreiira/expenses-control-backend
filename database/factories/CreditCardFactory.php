<?php

namespace Database\Factories;

use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\CreditCard;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CreditCardFactory extends Factory
{
    protected $model = CreditCard::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'bank_account_id' => BankAccount::factory(),
            'name' => fake()->words(2, true).' Card',
            'limit_amount' => fake()->randomFloat(2, 500, 10000),
            'closing_day' => fake()->numberBetween(1, 28),
            'due_day' => fake()->numberBetween(1, 28),
            'color' => '#6366f1',
            'is_active' => true,
        ];
    }
}
