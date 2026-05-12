<?php

namespace App\Domains\Calendar\Policies;

use App\Domains\Calendar\Models\CalendarEvent;
use App\Models\User;

class CalendarEventPolicy
{
    public function view(User $user, CalendarEvent $event): bool
    {
        return $user->id === $event->user_id;
    }

    public function update(User $user, CalendarEvent $event): bool
    {
        return $user->id === $event->user_id;
    }

    public function delete(User $user, CalendarEvent $event): bool
    {
        return $user->id === $event->user_id;
    }
}
