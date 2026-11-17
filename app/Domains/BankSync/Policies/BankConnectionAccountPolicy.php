<?php

namespace App\Domains\BankSync\Policies;

use App\Domains\BankSync\Models\BankConnectionAccount;
use App\Models\User;

class BankConnectionAccountPolicy
{
    public function view(User $user, BankConnectionAccount $account): bool
    {
        return $user->id === $account->user_id;
    }

    public function update(User $user, BankConnectionAccount $account): bool
    {
        return $user->id === $account->user_id;
    }
}
