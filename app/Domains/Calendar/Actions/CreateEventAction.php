<?php

namespace App\Domains\Calendar\Actions;

use App\Domains\Calendar\DTOs\CalendarEventDTO;
use App\Domains\Calendar\Models\CalendarEvent;
use App\Models\User;

final class CreateEventAction
{
    public function execute(User $user, CalendarEventDTO $dto): CalendarEvent
    {
        return CalendarEvent::create([
            'user_id' => $user->id,
            'title' => $dto->title,
            'description' => $dto->description,
            'location' => $dto->location,
            'start_date' => $dto->startDate,
            'end_date' => $dto->endDate,
            'is_all_day' => $dto->isAllDay,
            'color' => $dto->color,
            'source' => $dto->source,
            'external_id' => $dto->externalId,
            'recurrence_rule' => $dto->recurrenceRule,
        ]);
    }
}
