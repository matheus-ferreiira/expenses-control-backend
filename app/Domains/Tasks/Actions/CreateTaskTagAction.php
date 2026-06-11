<?php

namespace App\Domains\Tasks\Actions;

use App\Domains\Tasks\DTOs\TaskTagDTO;
use App\Domains\Tasks\Models\TaskTag;
use App\Models\User;

final class CreateTaskTagAction
{
    public function execute(User $user, TaskTagDTO $dto): TaskTag
    {
        return TaskTag::create([
            'user_id' => $user->id,
            'name' => $dto->name,
            'color' => $dto->color,
        ]);
    }
}
