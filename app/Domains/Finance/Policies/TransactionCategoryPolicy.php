<?php

namespace App\Domains\Finance\Policies;

use App\Domains\Finance\Models\TransactionCategory;
use App\Models\User;

class TransactionCategoryPolicy
{
    public function view(User $user, TransactionCategory $category): bool
    {
        return $user->id === $category->user_id;
    }

    public function update(User $user, TransactionCategory $category): bool
    {
        return $user->id === $category->user_id;
    }

    public function delete(User $user, TransactionCategory $category): bool
    {
        return $user->id === $category->user_id;
    }
}
