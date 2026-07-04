<?php

namespace App\Console\Commands;

use App\Domains\Tasks\Actions\RollRecurringTaskOccurrencesAction;
use App\Domains\Tasks\Enums\RecurrenceType;
use App\Domains\Tasks\Models\Task;
use Illuminate\Console\Command;

class RollRecurringTasks extends Command
{
    protected $signature = 'tasks:roll-recurrences';

    protected $description = 'Materializa a ocorrência de hoje das tarefas recorrentes cuja última ocorrência ficou no passado';

    public function handle(RollRecurringTaskOccurrencesAction $action): int
    {
        $userIds = Task::withTrashed()
            ->whereNotNull('recurrence_type')
            ->where('recurrence_type', '!=', RecurrenceType::None->value)
            ->distinct()
            ->pluck('user_id');

        $created = 0;

        foreach ($userIds as $userId) {
            $created += $action->executeForUser($userId);
        }

        $this->info("Ocorrências materializadas: {$created}");

        return self::SUCCESS;
    }
}
