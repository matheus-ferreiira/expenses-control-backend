<?php

namespace App\Domains\Tasks\Controllers;

use App\Domains\Tasks\DTOs\TaskListDTO;
use App\Domains\Tasks\Models\TaskList;
use App\Domains\Tasks\Requests\StoreTaskListRequest;
use App\Domains\Tasks\Requests\UpdateTaskListRequest;
use App\Domains\Tasks\Resources\TaskListResource;
use App\Domains\Tasks\Services\TaskService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskListController extends Controller
{
    public function __construct(
        private readonly TaskService $taskService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $lists = $this->taskService->listAllLists($request->user());

        return $this->success(TaskListResource::collection($lists));
    }

    public function store(StoreTaskListRequest $request): JsonResponse
    {
        $list = $this->taskService->createList(
            $request->user(),
            TaskListDTO::fromArray($request->validated()),
        );

        return $this->created(new TaskListResource($list), 'Lista criada');
    }

    public function update(UpdateTaskListRequest $request, TaskList $taskList): JsonResponse
    {
        $this->authorize('update', $taskList);

        $list = $this->taskService->updateList(
            $taskList,
            TaskListDTO::fromArray($request->validated()),
        );

        return $this->success(new TaskListResource($list), 'Lista atualizada');
    }

    public function destroy(Request $request, TaskList $taskList): JsonResponse
    {
        $this->authorize('delete', $taskList);
        $this->taskService->deleteList($taskList);

        return $this->noContent();
    }
}
