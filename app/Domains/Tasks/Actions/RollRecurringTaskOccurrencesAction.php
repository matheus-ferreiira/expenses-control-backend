<?php

namespace App\Domains\Tasks\Actions;

use App\Domains\Tasks\Enums\RecurrenceType;
use App\Domains\Tasks\Enums\TaskStatus;
use App\Domains\Tasks\Models\Task;
use App\Domains\Tasks\Services\RecurrenceService;
use Illuminate\Support\Collection;

/**
 * Materializa a ocorrência de HOJE (ou a próxima futura) de cada série
 * recorrente cuja última ocorrência ficou no passado — mesmo que ela
 * nunca tenha sido concluída. Sem isso, pular um dia mata a rotina:
 * a próxima ocorrência só nascia ao concluir a anterior.
 *
 * Regras:
 * - dias perdidos não são recriados (não empilha atrasadas artificiais);
 *   apenas UMA nova ocorrência por série, na primeira data >= hoje do padrão
 * - âncora = maior due_date da série INCLUINDO deletadas (ocorrência
 *   deletada = dia pulado de propósito; não ressuscita)
 * - série morta (todas as ocorrências deletadas) ou arquivada não rola
 */
final class RollRecurringTaskOccurrencesAction
{
    private const MAX_CATCH_UP_STEPS = 500;

    public function __construct(
        private readonly RecurrenceService $recurrenceService,
    ) {}

    public function executeForUser(string $userId): int
    {
        $recurring = Task::withTrashed()
            ->forUser($userId)
            ->whereNotNull('recurrence_type')
            ->where('recurrence_type', '!=', RecurrenceType::None->value)
            ->whereNotNull('due_date')
            ->orderBy('due_date')
            ->get();

        if ($recurring->isEmpty()) {
            return 0;
        }

        $created = 0;

        foreach ($recurring->groupBy(fn (Task $t) => $t->parent_task_id ?? $t->id) as $series) {
            if ($this->rollSeries($series)) {
                $created++;
            }
        }

        return $created;
    }

    /** @param  Collection<int, Task>  $series */
    private function rollSeries($series): bool
    {
        // Série viva = pelo menos uma ocorrência não deletada
        $alive = $series->filter(fn (Task $t) => $t->deleted_at === null);
        if ($alive->isEmpty()) {
            return false;
        }

        // Última ocorrência (inclui deletadas — âncora do padrão)
        $anchor = $series->sortByDesc('due_date')->first();
        if ($anchor->due_date->gte(today())) {
            return false; // já existe ocorrência de hoje ou futura
        }

        // Template: última ocorrência viva (título/prioridade/labels atuais)
        $template = $alive->sortByDesc('due_date')->first();
        if ($template->is_archived) {
            return false; // série arquivada não rola
        }

        $config = $template->recurrence_config ?? [];
        $type = $template->recurrence_type;

        $nextDate = $this->recurrenceService->calculateNextDate($type, $config, $anchor->due_date);
        $steps = 0;
        while ($nextDate->lt(today()) && $steps < self::MAX_CATCH_UP_STEPS) {
            $nextDate = $this->recurrenceService->calculateNextDate($type, $config, $nextDate);
            $steps++;
        }

        if ($nextDate->lt(today())) {
            return false; // padrão nunca alcançou hoje (guard)
        }

        $rootId = $template->parent_task_id ?? $template->id;

        $next = Task::create([
            'user_id' => $template->user_id,
            'parent_task_id' => $rootId,
            'task_list_id' => $template->task_list_id,
            'title' => $template->title,
            'description' => $template->description,
            'priority' => $template->priority,
            'status' => TaskStatus::Pending,
            'due_date' => $nextDate,
            'has_due_time' => $template->has_due_time,
            'recurrence_type' => $type,
            'recurrence_config' => $template->recurrence_config,
            'next_occurrence_date' => $nextDate,
            'position' => $template->position,
            'estimated_minutes' => $template->estimated_minutes,
        ]);

        $template->loadMissing(['labels', 'tags']);

        if ($template->labels->isNotEmpty()) {
            $next->labels()->sync($template->labels->pluck('id'));
        }

        if ($template->tags->isNotEmpty()) {
            $next->tags()->sync($template->tags->pluck('id'));
        }

        return true;
    }
}
