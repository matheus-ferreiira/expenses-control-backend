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
}
