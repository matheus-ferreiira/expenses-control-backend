<?php

namespace App\Domains\Calendar\Services;

use App\Domains\Calendar\Actions\CreateEventAction;
use App\Domains\Calendar\DTOs\CalendarEventDTO;
use App\Domains\Calendar\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class CalendarService
{
    public function __construct(
        private readonly CreateEventAction $createEvent,
    ) {}

    public function getEventsInRange(User $user, string $startDate, string $endDate): Collection
    {
        return CalendarEvent::forUser($user->id)
            ->inRange($startDate, $endDate)
            ->orderBy('start_date')
            ->get();
    }

    public function getUpcomingEvents(User $user, int $days = 7): Collection
    {
        return CalendarEvent::forUser($user->id)
            ->upcoming($days)
            ->orderBy('start_date')
            ->get();
    }

    public function create(User $user, CalendarEventDTO $dto): CalendarEvent
    {
        return $this->createEvent->execute($user, $dto);
    }

    public function update(CalendarEvent $event, CalendarEventDTO $dto): CalendarEvent
    {
        $event->update([
            'title' => $dto->title,
            'description' => $dto->description,
            'start_date' => $dto->startDate,
            'end_date' => $dto->endDate,
            'is_all_day' => $dto->isAllDay,
            'color' => $dto->color,
        ]);
        return $event;
    }

    public function delete(CalendarEvent $event): void
    {
        $event->delete();
    }
}
