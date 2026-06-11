<?php

namespace App\Domains\Tasks\Controllers;

use App\Domains\Tasks\DTOs\TaskTagDTO;
use App\Domains\Tasks\Models\TaskTag;
use App\Domains\Tasks\Requests\StoreTaskTagRequest;
use App\Domains\Tasks\Requests\UpdateTaskTagRequest;
use App\Domains\Tasks\Resources\TaskTagResource;
use App\Domains\Tasks\Services\TaskService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskTagController extends Controller
{
    public function __construct(
        private readonly TaskService $taskService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tags = $this->taskService->listAllTags($request->user());

        return $this->success(TaskTagResource::collection($tags));
    }

    public function store(StoreTaskTagRequest $request): JsonResponse
    {
        $tag = $this->taskService->createTag(
            $request->user(),
            TaskTagDTO::fromArray($request->validated()),
        );

        return $this->created(new TaskTagResource($tag), 'Tag criada');
    }

    public function update(UpdateTaskTagRequest $request, TaskTag $taskTag): JsonResponse
    {
        $this->authorize('update', $taskTag);

        $tag = $this->taskService->updateTag(
            $taskTag,
            TaskTagDTO::fromArray($request->validated()),
        );

        return $this->success(new TaskTagResource($tag), 'Tag atualizada');
    }

    public function destroy(Request $request, TaskTag $taskTag): JsonResponse
    {
        $this->authorize('delete', $taskTag);
        $this->taskService->deleteTag($taskTag);

        return $this->noContent();
    }
}
