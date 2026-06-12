<?php

namespace App\Domains\Tasks\Controllers;

use App\Domains\Tasks\DTOs\TaskDTO;
use App\Domains\Tasks\Models\Task;
use App\Domains\Tasks\Requests\StoreTaskRequest;
use App\Domains\Tasks\Requests\TaskFilterRequest;
use App\Domains\Tasks\Requests\UpdateTaskRequest;
use App\Domains\Tasks\Resources\TaskResource;
use App\Domains\Tasks\Services\TaskService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function __construct(
        private readonly TaskService $taskService,
    ) {}

    public function index(TaskFilterRequest $request): JsonResponse
    {
        $tasks = $this->taskService->list($request->user(), $request->validated());

        return $this->paginatedSuccess(TaskResource::collection($tasks));
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = $this->taskService->create(
            $request->user(),
            TaskDTO::fromArray($request->validated())
        );

        return $this->created(new TaskResource($task), 'Task created');
    }

    public function show(Request $request, Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        return $this->success(new TaskResource($task->load(['labels', 'subtasks'])));
    }

    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);
        $task = $this->taskService->update($task, TaskDTO::fromArray($request->validated()));

        return $this->success(new TaskResource($task), 'Task updated');
    }

    public function destroy(Request $request, Task $task): JsonResponse
    {
        $this->authorize('delete', $task);
        $this->taskService->delete($task);

        return $this->noContent();
    }

    public function complete(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);
        $task = $this->taskService->complete($task);

        return $this->success(new TaskResource($task), 'Task completed');
    }

    public function archive(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);
        $task = $this->taskService->archive($task, true);

        return $this->success(new TaskResource($task), 'Task archived');
    }

    public function unarchive(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);
        $task = $this->taskService->archive($task, false);

        return $this->success(new TaskResource($task), 'Task unarchived');
    }

    public function reorder(Request $request): JsonResponse
    {
        $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['uuid']]);
        $this->taskService->reorder($request->user(), $request->ids);

        return $this->success(message: 'Tasks reordered');
    }

    public function recurrenceHistory(Request $request, Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        $rootId = $task->parent_task_id ?? $task->id;

        $occurrences = Task::withTrashed()
            ->where(function ($q) use ($rootId) {
                $q->where('id', $rootId)->orWhere('parent_task_id', $rootId);
            })
            ->orderBy('due_date')
            ->get();

        $totalCount = $occurrences->count();
        $completed = $occurrences->filter(fn ($t) => $t->status?->value === 'completed')
            ->sortByDesc('completed_at')
            ->values();
        $completedCount = $completed->count();

        // Streak: count consecutive completions from end of due_date-sorted list
        $streak = 0;
        foreach ($occurrences->reverse() as $occ) {
            if ($occ->status?->value === 'completed') {
                $streak++;
            } else {
                break;
            }
        }

        $recentCompletions = $completed->take(10)->map(fn ($t) => [
            'id' => $t->id,
            'completed_at' => $t->completed_at?->toISOString(),
            'due_date' => $t->due_date?->toDateString(),
        ])->values();

        return $this->success([
            'total_count' => $totalCount,
            'completed_count' => $completedCount,
            'completion_rate' => $totalCount > 0 ? (int) round(($completedCount / $totalCount) * 100) : 0,
            'current_streak' => $streak,
            'recent_completions' => $recentCompletions,
        ]);
    }
}
