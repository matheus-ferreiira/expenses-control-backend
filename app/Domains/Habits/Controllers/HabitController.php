<?php

namespace App\Domains\Habits\Controllers;

use App\Domains\Habits\DTOs\HabitDTO;
use App\Domains\Habits\DTOs\HabitLogDTO;
use App\Domains\Habits\Models\Habit;
use App\Domains\Habits\Requests\StoreHabitRequest;
use App\Domains\Habits\Requests\UpdateHabitRequest;
use App\Domains\Habits\Resources\HabitResource;
use App\Domains\Habits\Services\HabitService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HabitController extends Controller
{
    public function __construct(
        private readonly HabitService $habitService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $habits = $this->habitService->list($request->user(), $request->query());

        return $this->paginatedSuccess(HabitResource::collection($habits));
    }

    public function today(Request $request): JsonResponse
    {
        $habits = $this->habitService->getTodayHabits($request->user());

        return $this->success(HabitResource::collection($habits));
    }

    public function store(StoreHabitRequest $request): JsonResponse
    {
        $habit = $this->habitService->create(
            $request->user(),
            HabitDTO::fromArray($request->validated())
        );

        return $this->created(new HabitResource($habit), 'Habit created');
    }

    public function show(Request $request, Habit $habit): JsonResponse
    {
        $this->authorize('view', $habit);

        return $this->success(new HabitResource($habit->load('logs')));
    }

    public function update(UpdateHabitRequest $request, Habit $habit): JsonResponse
    {
        $this->authorize('update', $habit);
        $habit = $this->habitService->update($habit, HabitDTO::fromArray($request->validated()));

        return $this->success(new HabitResource($habit), 'Habit updated');
    }

    public function destroy(Request $request, Habit $habit): JsonResponse
    {
        $this->authorize('delete', $habit);
        $this->habitService->delete($habit);

        return $this->noContent();
    }

    public function archive(Request $request, Habit $habit): JsonResponse
    {
        $this->authorize('update', $habit);
        $habit = $this->habitService->archive($habit, true);

        return $this->success(new HabitResource($habit), 'Habit archived');
    }

    public function unarchive(Request $request, Habit $habit): JsonResponse
    {
        $this->authorize('update', $habit);
        $habit = $this->habitService->archive($habit, false);

        return $this->success(new HabitResource($habit), 'Habit unarchived');
    }

    public function log(Request $request, Habit $habit): JsonResponse
    {
        $this->authorize('update', $habit);
        $data = $request->validate([
            'completed_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);
        $this->habitService->log($habit, HabitLogDTO::fromArray($data));

        return $this->success(new HabitResource($habit->fresh()->load('logs')), 'Habit logged');
    }

    public function unlog(Request $request, Habit $habit): JsonResponse
    {
        $this->authorize('update', $habit);
        $request->validate(['date' => ['required', 'date']]);
        $this->habitService->unlog($habit, $request->date);

        return $this->success(message: 'Log removed');
    }

    public function stats(Request $request, Habit $habit): JsonResponse
    {
        $this->authorize('view', $habit);

        return $this->success([
            'habit' => new HabitResource($habit),
            'stats' => $this->habitService->getStats($habit),
        ]);
    }

    public function heatmap(Request $request, Habit $habit): JsonResponse
    {
        $this->authorize('view', $habit);
        $days = (int) $request->query('days', 365);

        return $this->success($this->habitService->getHeatmap($habit, $days));
    }
}
