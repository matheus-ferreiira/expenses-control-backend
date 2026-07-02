<?php

namespace App\Domains\Prices\Policies;

use App\Domains\Prices\Models\PriceCategory;
use App\Models\User;

class PriceCategoryPolicy
{
    public function view(User $user, PriceCategory $category): bool
    {
        return $user->id === $category->user_id;
    }

    public function update(User $user, PriceCategory $category): bool
    {
        return $user->id === $category->user_id;
    }

    public function delete(User $user, PriceCategory $category): bool
    {
        return $user->id === $category->user_id;
    }
}
