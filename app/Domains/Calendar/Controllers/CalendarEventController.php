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
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $events = $this->calendarService->getEventsInRange(
            $request->user(),
            $validated['start_date'],
            $validated['end_date']
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

        $data = array_merge([
            'title' => $calendarEvent->title,
            'description' => $calendarEvent->description,
            'location' => $calendarEvent->location,
            'start_date' => $calendarEvent->start_date,
            'end_date' => $calendarEvent->end_date,
            'is_all_day' => $calendarEvent->is_all_day,
            'color' => $calendarEvent->color,
            'recurrence_rule' => $calendarEvent->recurrence_rule,
        ], $request->validated());

        $event = $this->calendarService->update($calendarEvent, CalendarEventDTO::fromArray($data));

        return $this->success(new CalendarEventResource($event), 'Event updated');
    }

    public function destroy(Request $request, CalendarEvent $calendarEvent): JsonResponse
    {
        $this->authorize('delete', $calendarEvent);
        $this->calendarService->delete($calendarEvent);

        return $this->noContent();
    }
}
