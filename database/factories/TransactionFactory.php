<?php

namespace Database\Factories;

use App\Domains\Finance\Enums\TransactionType;
use App\Domains\Finance\Models\BankAccount;
use App\Domains\Finance\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => TransactionType::Expense,
            'amount' => fake()->randomFloat(2, 10, 500),
            'description' => fake()->sentence(3),
            'transaction_date' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
        ];
    }

    public function income(): static
    {
        return $this->state(['type' => TransactionType::Income]);
    }

    public function expense(): static
    {
        return $this->state(['type' => TransactionType::Expense]);
    }

    public function withAccount(BankAccount $account): static
    {
        return $this->state(['account_id' => $account->id, 'user_id' => $account->user_id]);
    }
}
