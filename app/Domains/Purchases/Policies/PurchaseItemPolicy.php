<?php

namespace App\Domains\Purchases\Policies;

use App\Domains\Purchases\Models\PurchaseItem;
use App\Models\User;

class PurchaseItemPolicy
{
    public function view(User $user, PurchaseItem $item): bool
    {
        return $user->id === $item->user_id;
    }

    public function update(User $user, PurchaseItem $item): bool
    {
        return $user->id === $item->user_id;
    }

    public function delete(User $user, PurchaseItem $item): bool
    {
        return $user->id === $item->user_id;
    }
}
