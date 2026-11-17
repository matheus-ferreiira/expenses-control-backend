<?php

namespace Database\Factories;

use App\Domains\BankSync\Models\BankConnection;
use App\Domains\BankSync\Models\BankConnectionAccount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BankConnectionAccountFactory extends Factory
{
    protected $model = BankConnectionAccount::class;

    public function definition(): array
    {
        return [
            'bank_connection_id' => BankConnection::factory(),
            'user_id' => fn (array $attributes) => BankConnection::find($attributes['bank_connection_id'])->user_id,
            'external_account_id' => (string) Str::uuid(),
            'type' => 'BANK',
            'subtype' => 'CHECKING_ACCOUNT',
            'name' => 'Conta corrente',
            'currency_code' => 'BRL',
            'balance_cents' => 100000,
        ];
    }
}
