<?php

namespace App\Domains\Tasks\Actions;

use App\Domains\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ReorderTasksAction
{
    public function execute(User $user, array $orderedIds): void
    {
        DB::transaction(function () use ($user, $orderedIds) {
            foreach ($orderedIds as $position => $taskId) {
                Task::forUser($user->id)
                    ->where('id', $taskId)
                    ->update(['position' => $position + 1]);
            }
        });
    }
}
