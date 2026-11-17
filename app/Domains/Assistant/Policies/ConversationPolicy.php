<?php

namespace App\Domains\Assistant\Policies;

use App\Domains\Assistant\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return $user->id === $conversation->user_id;
    }
}
