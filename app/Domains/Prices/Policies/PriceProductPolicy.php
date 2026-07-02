<?php

namespace App\Domains\Prices\Policies;

use App\Domains\Prices\Models\PriceProduct;
use App\Models\User;

class PriceProductPolicy
{
    public function view(User $user, PriceProduct $product): bool
    {
        return $user->id === $product->user_id;
    }

    public function update(User $user, PriceProduct $product): bool
    {
        return $user->id === $product->user_id;
    }

    public function delete(User $user, PriceProduct $product): bool
    {
        return $user->id === $product->user_id;
    }
}
