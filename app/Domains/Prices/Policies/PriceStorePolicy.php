<?php

namespace App\Domains\Prices\Policies;

use App\Domains\Prices\Models\PriceStore;
use App\Models\User;

class PriceStorePolicy
{
    public function view(User $user, PriceStore $store): bool
    {
        return $user->id === $store->user_id;
    }

    public function update(User $user, PriceStore $store): bool
    {
        return $user->id === $store->user_id;
    }

    public function delete(User $user, PriceStore $store): bool
    {
        return $user->id === $store->user_id;
    }
}
