<?php

namespace App\Domains\Tasks\Controllers;

use App\Domains\Tasks\Models\Subtask;
use App\Domains\Tasks\Models\Task;
use App\Domains\Tasks\Resources\SubtaskResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubtaskController extends Controller
{
    public function store(Request $request, Task $task): JsonResponse
    {
        abort_unless($task->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'position' => ['nullable', 'integer'],
        ]);

        $subtask = $task->subtasks()->create([
            'title' => $data['title'],
            'position' => $data['position'] ?? $task->subtasks()->count() + 1,
        ]);

        return $this->created(new SubtaskResource($subtask));
    }

    public function update(Request $request, Task $task, Subtask $subtask): JsonResponse
    {
        abort_unless($task->user_id === $request->user()->id, 403);
        abort_unless($subtask->task_id === $task->id, 404);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:500'],
            'completed' => ['sometimes', 'boolean'],
        ]);

        $subtask->update($data);

        return $this->success(new SubtaskResource($subtask));
    }

    public function destroy(Request $request, Task $task, Subtask $subtask): JsonResponse
    {
        abort_unless($task->user_id === $request->user()->id, 403);
        abort_unless($subtask->task_id === $task->id, 404);
        $subtask->delete();

        return $this->noContent();
    }
}
