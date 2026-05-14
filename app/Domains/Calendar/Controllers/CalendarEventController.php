<?php

namespace App\Domains\Calendar\Controllers;

use App\Domains\Calendar\DTOs\CalendarEventDTO;
use App\Domains\Calendar\Models\CalendarEvent;
use App\Domains\Calendar\Requests\StoreCalendarEventRequest;
use App\Domains\Calendar\Requests\UpdateCalendarEventRequest;
use App\Domains\Calendar\Resources\CalendarEventResource;
use App\Domains\Calendar\Services\CalendarService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarEventController extends Controller
{
    public function __construct(
        private readonly CalendarService $calendarService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $events = $this->calendarService->getEventsInRange(
            $request->user(),
            $request->start_date,
            $request->end_date
        );

        return $this->success(CalendarEventResource::collection($events));
    }

    public function upcoming(Request $request): JsonResponse
    {
        $days = (int) $request->query('days', 7);
        $events = $this->calendarService->getUpcomingEvents($request->user(), $days);

        return $this->success(CalendarEventResource::collection($events));
    }

    public function store(StoreCalendarEventRequest $request): JsonResponse
    {
        $event = $this->calendarService->create(
            $request->user(),
            CalendarEventDTO::fromArray($request->validated())
        );

        return $this->created(new CalendarEventResource($event), 'Event created');
    }

    public function show(Request $request, CalendarEvent $calendarEvent): JsonResponse
    {
        $this->authorize('view', $calendarEvent);

        return $this->success(new CalendarEventResource($calendarEvent));
    }

    public function update(UpdateCalendarEventRequest $request, CalendarEvent $calendarEvent): JsonResponse
    {
        $this->authorize('update', $calendarEvent);
        $event = $this->calendarService->update($calendarEvent, CalendarEventDTO::fromArray($request->validated()));

        return $this->success(new CalendarEventResource($event), 'Event updated');
    }

    public function destroy(Request $request, CalendarEvent $calendarEvent): JsonResponse
    {
        $this->authorize('delete', $calendarEvent);
        $this->calendarService->delete($calendarEvent);

        return $this->noContent();
    }
}
