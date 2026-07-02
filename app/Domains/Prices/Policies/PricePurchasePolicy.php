<?php

namespace App\Domains\Prices\Policies;

use App\Domains\Prices\Models\PricePurchase;
use App\Models\User;

class PricePurchasePolicy
{
    public function view(User $user, PricePurchase $purchase): bool
    {
        return $user->id === $purchase->user_id;
    }

    public function update(User $user, PricePurchase $purchase): bool
    {
        return $user->id === $purchase->user_id;
    }

    public function delete(User $user, PricePurchase $purchase): bool
    {
        return $user->id === $purchase->user_id;
    }
}
