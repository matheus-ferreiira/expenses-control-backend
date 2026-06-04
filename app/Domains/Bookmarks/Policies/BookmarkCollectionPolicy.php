<?php

namespace App\Domains\Bookmarks\Policies;

use App\Domains\Bookmarks\Models\BookmarkCollection;
use App\Models\User;

class BookmarkCollectionPolicy
{
    public function view(User $user, BookmarkCollection $collection): bool
    {
        return $user->id === $collection->user_id;
    }

    public function update(User $user, BookmarkCollection $collection): bool
    {
        return $user->id === $collection->user_id;
    }

    public function delete(User $user, BookmarkCollection $collection): bool
    {
        return $user->id === $collection->user_id;
    }
}
