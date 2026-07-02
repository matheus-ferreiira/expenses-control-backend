<?php

namespace App\Domains\Prices\Policies;

use App\Domains\Prices\Models\PriceSale;
use App\Models\User;

class PriceSalePolicy
{
    public function view(User $user, PriceSale $sale): bool
    {
        return $user->id === $sale->user_id;
    }

    public function update(User $user, PriceSale $sale): bool
    {
        return $user->id === $sale->user_id;
    }

    public function delete(User $user, PriceSale $sale): bool
    {
        return $user->id === $sale->user_id;
    }
}
