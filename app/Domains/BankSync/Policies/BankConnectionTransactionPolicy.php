<?php

namespace App\Domains\BankSync\Policies;

use App\Domains\BankSync\Models\BankConnectionTransaction;
use App\Models\User;

class BankConnectionTransactionPolicy
{
    public function update(User $user, BankConnectionTransaction $transaction): bool
    {
        return $user->id === $transaction->user_id;
    }
}
