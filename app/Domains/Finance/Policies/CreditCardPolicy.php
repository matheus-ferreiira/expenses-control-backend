<?php

namespace App\Domains\Finance\Policies;

use App\Domains\Finance\Models\CreditCard;
use App\Models\User;

class CreditCardPolicy
{
    public function view(User $user, CreditCard $card): bool
    {
        return $user->id === $card->user_id;
    }

    public function update(User $user, CreditCard $card): bool
    {
        return $user->id === $card->user_id;
    }

    public function delete(User $user, CreditCard $card): bool
    {
        return $user->id === $card->user_id;
    }
}
