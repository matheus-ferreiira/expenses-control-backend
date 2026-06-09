<?php

namespace App\Domains\Tasks\Services;

use App\Domains\Tasks\Actions\ArchiveTaskAction;
use App\Domains\Tasks\Actions\CompleteTaskAction;
use App\Domains\Tasks\Actions\CreateTaskAction;
use App\Domains\Tasks\Actions\ReorderTasksAction;
use App\Domains\Tasks\Actions\UpdateTaskAction;
use App\Domains\Tasks\DTOs\TaskDTO;
use App\Domains\Tasks\Models\Task;
use App\Domains\Tasks\Models\TaskLabel;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

final class TaskService
{
    public function __construct(
        private readonly CreateTaskAction $createTask,
        private readonly UpdateTaskAction $updateTask,
        private readonly CompleteTaskAction $completeTask,
        private readonly ArchiveTaskAction $archiveTask,
        private readonly ReorderTasksAction $reorderTasks,
    ) {}

    public function list(User $user, array $filters = []): LengthAwarePaginator
    {
        $showArchived = isset($filters['archived']) && $filters['archived'];

        $query = Task::forUser($user->id)
            ->with(['labels', 'subtasks']);

        if ($showArchived) {
            $query->where('is_archived', true);
        } else {
            $query->notArchived();
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['label_id'])) {
            $query->whereHas('labels', fn ($q) => $q->where('task_labels.id', $filters['label_id']));
        }

        if (! empty($filters['search'])) {
            $query->where('title', 'like', "%{$filters['search']}%");
        }

        if (! empty($filters['due_date'])) {
            $query->whereDate('due_date', $filters['due_date']);
        }

        $allowedSorts = ['position', 'created_at', 'due_date', 'priority', 'title', 'status'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSorts, true)
            ? $filters['sort_by']
            : 'position';
        $sortDir = strtolower($filters['sort_direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortBy, $sortDir);

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function create(User $user, TaskDTO $dto): Task
    {
        return $this->createTask->execute($user, $dto);
    }

    public function update(Task $task, TaskDTO $dto): Task
    {
        return $this->updateTask->execute($task, $dto);
    }

    public function complete(Task $task): Task
    {
        return $this->completeTask->execute($task);
    }

    public function archive(Task $task, bool $archive = true): Task
    {
        return $this->archiveTask->execute($task, $archive);
    }

    public function delete(Task $task): void
    {
        $task->delete();
    }

    public function reorder(User $user, array $orderedIds): void
    {
        $this->reorderTasks->execute($user, $orderedIds);
    }

    public function listLabels(User $user): Collection
    {
        return TaskLabel::forUser($user->id)->get();
    }

    public function createLabel(User $user, array $data): TaskLabel
    {
        return TaskLabel::create([...$data, 'user_id' => $user->id]);
    }

    public function updateLabel(TaskLabel $label, array $data): TaskLabel
    {
        $label->update($data);

        return $label;
    }

    public function deleteLabel(TaskLabel $label): void
    {
        $label->delete();
    }
}
