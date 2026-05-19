<?php

namespace App\Domains\Tasks\Controllers;

use App\Domains\Tasks\Models\TaskLabel;
use App\Domains\Tasks\Resources\TaskLabelResource;
use App\Domains\Tasks\Services\TaskService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskLabelController extends Controller
{
    public function __construct(
        private readonly TaskService $taskService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $labels = $this->taskService->listLabels($request->user());

        return $this->success(TaskLabelResource::collection($labels));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        $label = $this->taskService->createLabel($request->user(), $data);

        return $this->created(new TaskLabelResource($label), 'Label created');
    }

    public function update(Request $request, TaskLabel $taskLabel): JsonResponse
    {
        $this->authorize('update', $taskLabel);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        $label = $this->taskService->updateLabel($taskLabel, $data);

        return $this->success(new TaskLabelResource($label), 'Label updated');
    }

    public function destroy(Request $request, TaskLabel $taskLabel): JsonResponse
    {
        $this->authorize('delete', $taskLabel);
        $this->taskService->deleteLabel($taskLabel);

        return $this->noContent();
    }
}
