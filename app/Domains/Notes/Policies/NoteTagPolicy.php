<?php

namespace App\Domains\Notes\Policies;

use App\Domains\Notes\Models\NoteTag;
use App\Models\User;

class NoteTagPolicy
{
    public function view(User $user, NoteTag $tag): bool
    {
        return $user->id === $tag->user_id;
    }

    public function update(User $user, NoteTag $tag): bool
    {
        return $user->id === $tag->user_id;
    }

    public function delete(User $user, NoteTag $tag): bool
    {
        return $user->id === $tag->user_id;
    }
}
