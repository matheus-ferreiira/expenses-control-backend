<?php

namespace App\Domains\Bookmarks\Policies;

use App\Domains\Bookmarks\Models\Bookmark;
use App\Models\User;

class BookmarkPolicy
{
    public function view(User $user, Bookmark $bookmark): bool
    {
        return $user->id === $bookmark->user_id;
    }

    public function update(User $user, Bookmark $bookmark): bool
    {
        return $user->id === $bookmark->user_id;
    }

    public function delete(User $user, Bookmark $bookmark): bool
    {
        return $user->id === $bookmark->user_id;
    }
}
