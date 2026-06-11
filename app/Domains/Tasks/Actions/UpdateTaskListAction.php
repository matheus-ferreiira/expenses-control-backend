<?php

namespace App\Domains\Tasks\Actions;

use App\Domains\Tasks\DTOs\TaskListDTO;
use App\Domains\Tasks\Models\TaskList;

final class UpdateTaskListAction
{
    public function execute(TaskList $list, TaskListDTO $dto): TaskList
    {
        if ($dto->isDefault) {
            TaskList::forUser($list->user_id)->where('is_default', true)
                ->where('id', '!=', $list->id)
                ->update(['is_default' => false]);
        }

        $list->update(array_filter([
            'name' => $dto->name,
            'color' => $dto->color,
            'icon' => $dto->icon,
            'position' => $dto->position,
            'is_default' => $dto->isDefault,
        ], fn ($v) => $v !== null));

        return $list->fresh();
    }
}
