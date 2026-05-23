<?php

namespace App\Domains\Finance\Policies;

use App\Domains\Finance\Models\TransactionTag;
use App\Models\User;

class TransactionTagPolicy
{
    public function update(User $user, TransactionTag $tag): bool
    {
        return $user->id === $tag->user_id;
    }

    public function delete(User $user, TransactionTag $tag): bool
    {
        return $user->id === $tag->user_id;
    }
}
