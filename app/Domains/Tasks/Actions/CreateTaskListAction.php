<?php

namespace App\Domains\Tasks\Actions;

use App\Domains\Tasks\DTOs\TaskListDTO;
use App\Domains\Tasks\Models\TaskList;
use App\Models\User;

final class CreateTaskListAction
{
    public function execute(User $user, TaskListDTO $dto): TaskList
    {
        if ($dto->isDefault) {
            TaskList::forUser($user->id)->where('is_default', true)->update(['is_default' => false]);
        }

        $position = $dto->position
            ?? (TaskList::forUser($user->id)->max('position') ?? 0) + 1;

        return TaskList::create([
            'user_id' => $user->id,
            'name' => $dto->name,
            'color' => $dto->color,
            'icon' => $dto->icon,
            'position' => $position,
            'is_default' => $dto->isDefault ?? false,
        ]);
    }
}
