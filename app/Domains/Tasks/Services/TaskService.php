<?php

namespace App\Domains\Tasks\Services;

use App\Domains\Tasks\Actions\ArchiveTaskAction;
use App\Domains\Tasks\Actions\CompleteTaskAction;
use App\Domains\Tasks\Actions\CreateTaskAction;
use App\Domains\Tasks\Actions\CreateTaskListAction;
use App\Domains\Tasks\Actions\CreateTaskTagAction;
use App\Domains\Tasks\Actions\ReorderTasksAction;
use App\Domains\Tasks\Actions\UpdateTaskAction;
use App\Domains\Tasks\Actions\UpdateTaskListAction;
use App\Domains\Tasks\Actions\UpdateTaskTagAction;
use App\Domains\Tasks\DTOs\TaskDTO;
use App\Domains\Tasks\DTOs\TaskListDTO;
use App\Domains\Tasks\DTOs\TaskTagDTO;
use App\Domains\Tasks\Models\Task;
use App\Domains\Tasks\Models\TaskLabel;
use App\Domains\Tasks\Models\TaskList;
use App\Domains\Tasks\Models\TaskTag;
use App\Models\User;
use Carbon\Carbon;
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
        private readonly CreateTaskListAction $createTaskList,
        private readonly UpdateTaskListAction $updateTaskList,
        private readonly CreateTaskTagAction $createTaskTag,
        private readonly UpdateTaskTagAction $updateTaskTag,
    ) {}

    public function list(User $user, array $filters = []): LengthAwarePaginator
    {
        $showArchived = isset($filters['archived']) && $filters['archived'];

        $query = Task::forUser($user->id)
            ->with(['labels', 'subtasks', 'tags', 'taskList']);

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

        if (! empty($filters['list_id'])) {
            $query->where('task_list_id', $filters['list_id']);
        }

        if (! empty($filters['tag_id'])) {
            $query->whereHas('tags', fn ($q) => $q->where('task_tags.id', $filters['tag_id']));
        }

        if (! empty($filters['period'])) {
            $today = Carbon::today();
            match ($filters['period']) {
                'today'    => $query->whereDate('due_date', $today),
                'upcoming' => $query->where('due_date', '>', $today)->whereDate('due_date', '>', $today),
                'overdue'  => $query->where('due_date', '<', $today)->where('status', '!=', 'completed'),
                'no_date'  => $query->whereNull('due_date'),
                default    => null,
            };
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

    public function listAllLists(User $user): Collection
    {
        return TaskList::forUser($user->id)->withCount('tasks')->orderBy('position')->get();
    }

    public function createList(User $user, TaskListDTO $dto): TaskList
    {
        return $this->createTaskList->execute($user, $dto);
    }

    public function updateList(TaskList $list, TaskListDTO $dto): TaskList
    {
        return $this->updateTaskList->execute($list, $dto);
    }

    public function deleteList(TaskList $list): void
    {
        $list->delete();
    }

    public function listAllTags(User $user): Collection
    {
        return TaskTag::forUser($user->id)->orderBy('name')->get();
    }

    public function createTag(User $user, TaskTagDTO $dto): TaskTag
    {
        return $this->createTaskTag->execute($user, $dto);
    }

    public function updateTag(TaskTag $tag, TaskTagDTO $dto): TaskTag
    {
        return $this->updateTaskTag->execute($tag, $dto);
    }

    public function deleteTag(TaskTag $tag): void
    {
        $tag->delete();
    }
}
