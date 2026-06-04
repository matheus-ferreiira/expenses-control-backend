<?php

namespace App\Domains\Bookmarks\Policies;

use App\Domains\Bookmarks\Models\BookmarkCategory;
use App\Models\User;

class BookmarkCategoryPolicy
{
    public function view(User $user, BookmarkCategory $category): bool
    {
        return $user->id === $category->user_id;
    }

    public function update(User $user, BookmarkCategory $category): bool
    {
        return $user->id === $category->user_id;
    }

    public function delete(User $user, BookmarkCategory $category): bool
    {
        return $user->id === $category->user_id;
    }
}
