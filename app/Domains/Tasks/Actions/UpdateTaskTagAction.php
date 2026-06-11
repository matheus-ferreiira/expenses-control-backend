<?php

namespace App\Domains\Tasks\Actions;

use App\Domains\Tasks\DTOs\TaskTagDTO;
use App\Domains\Tasks\Models\TaskTag;

final class UpdateTaskTagAction
{
    public function execute(TaskTag $tag, TaskTagDTO $dto): TaskTag
    {
        $tag->update(array_filter([
            'name' => $dto->name,
            'color' => $dto->color,
        ], fn ($v) => $v !== null));

        return $tag->fresh();
    }
}
