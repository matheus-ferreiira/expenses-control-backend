<?php

namespace App\Domains\Finance\Policies;

use App\Domains\Finance\Models\BankAccount;
use App\Models\User;

class BankAccountPolicy
{
    public function view(User $user, BankAccount $account): bool
    {
        return $user->id === $account->user_id;
    }

    public function update(User $user, BankAccount $account): bool
    {
        return $user->id === $account->user_id;
    }

    public function delete(User $user, BankAccount $account): bool
    {
        return $user->id === $account->user_id;
    }
}
