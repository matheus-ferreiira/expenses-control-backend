<?php

namespace Database\Factories;

use App\Domains\Finance\Enums\AccountType;
use App\Domains\Finance\Models\BankAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BankAccountFactory extends Factory
{
    protected $model = BankAccount::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'bank_name' => fake()->optional()->company(),
            'type' => AccountType::Checking,
            'balance' => fake()->randomFloat(2, 0, 10000),
            'currency' => 'BRL',
            'color' => '#6366f1',
            'is_active' => true,
        ];
    }
}
