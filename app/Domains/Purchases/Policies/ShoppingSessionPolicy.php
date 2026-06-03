<?php

namespace App\Domains\Purchases\Policies;

use App\Domains\Purchases\Models\ShoppingSession;
use App\Models\User;

class ShoppingSessionPolicy
{
    public function view(User $user, ShoppingSession $session): bool
    {
        return $user->id === $session->user_id;
    }

    public function update(User $user, ShoppingSession $session): bool
    {
        return $user->id === $session->user_id;
    }

    public function delete(User $user, ShoppingSession $session): bool
    {
        return $user->id === $session->user_id;
    }

    public function finish(User $user, ShoppingSession $session): bool
    {
        return $user->id === $session->user_id;
    }
}
