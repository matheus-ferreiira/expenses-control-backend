<?php

namespace Database\Factories;

use App\Domains\BankSync\Models\BankConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BankConnectionFactory extends Factory
{
    protected $model = BankConnection::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => 'pluggy',
            'external_item_id' => (string) Str::uuid(),
            'connector_id' => 200,
            'connector_name' => 'MeuPluggy',
            'status' => 'UPDATED',
            'execution_status' => 'SUCCESS',
        ];
    }
}
