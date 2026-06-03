<?php

namespace App\Domains\Purchases\Policies;

use App\Domains\Purchases\Models\ShoppingItem;
use App\Models\User;

class ShoppingItemPolicy
{
    public function update(User $user, ShoppingItem $item): bool
    {
        return $user->id === $item->user_id;
    }

    public function delete(User $user, ShoppingItem $item): bool
    {
        return $user->id === $item->user_id;
    }
}
