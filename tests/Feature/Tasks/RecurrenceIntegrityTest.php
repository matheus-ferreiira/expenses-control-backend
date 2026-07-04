<?php

namespace Tests\Feature\Tasks;

use App\Domains\Tasks\Actions\RollRecurringTaskOccurrencesAction;
use App\Domains\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A rotina não pode morrer: ocorrências de hoje existem mesmo quando a de
 * ontem não foi concluída, horários sobrevivem à recorrência e "sem hora"
 * não significa meia-noite.
 */
class RecurrenceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function dailyTask(User $user, array $overrides = []): Task
    {
        return Task::factory()->create([
            'user_id' => $user->id,
            'recurrence_type' => 'daily',
            'recurrence_config' => ['interval' => 1],
            ...$overrides,
        ]);
    }

    // ── Concluir atrasada não gera outra atrasada ─────────────────────────────

    public function test_completing_overdue_daily_task_generates_occurrence_today_not_in_past(): void
    {
        $user = User::factory()->create();
        $task = $this->dailyTask($user, [
            'due_date' => now()->subDays(3)->setTime(8, 0),
            'has_due_time' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/tasks/{$task->id}/complete")
            ->assertOk();

        $next = Task::where('parent_task_id', $task->id)->first();

        $this->assertNotNull($next);
        $this->assertTrue($next->due_date->isToday(), 'próxima ocorrência deve alcançar hoje, não ficar no passado');
        $this->assertEquals('08:00', $next->due_date->format('H:i'), 'horário da rotina deve sobreviver');
        $this->assertTrue($next->has_due_time);
    }

    public function test_completing_task_on_time_generates_next_with_same_time(): void
    {
        $user = User::factory()->create();
        $task = $this->dailyTask($user, [
            'due_date' => now()->setTime(7, 30),
            'has_due_time' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/tasks/{$task->id}/complete")
            ->assertOk();

        $next = Task::where('parent_task_id', $task->id)->first();

        $this->assertTrue($next->due_date->isTomorrow());
        $this->assertEquals('07:30', $next->due_date->format('H:i'));
    }

    // ── Roll diário: dia pulado não mata a rotina ─────────────────────────────

    public function test_listing_tasks_materializes_todays_occurrence_when_previous_was_skipped(): void
    {
        $user = User::factory()->create();
        $task = $this->dailyTask($user, [
            'due_date' => now()->subDays(3)->setTime(8, 0),
            'has_due_time' => true,
            // nunca concluída — ficou para trás
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/tasks')
            ->assertOk();

        $occurrences = Task::where('parent_task_id', $task->id)->get();

        $this->assertCount(1, $occurrences, 'apenas UMA nova ocorrência — dias perdidos não empilham');
        $this->assertTrue($occurrences->first()->due_date->isToday());
        $this->assertEquals('08:00', $occurrences->first()->due_date->format('H:i'));

        // A atrasada original continua visível (não é apagada nem movida)
        $this->assertNull($task->fresh()->deleted_at);
        $this->assertEquals('pending', $task->fresh()->status->value);
    }

    public function test_roll_is_idempotent(): void
    {
        $user = User::factory()->create();
        $task = $this->dailyTask($user, ['due_date' => now()->subDays(2)]);

        $action = app(RollRecurringTaskOccurrencesAction::class);

        $this->assertEquals(1, $action->executeForUser($user->id));
        $this->assertEquals(0, $action->executeForUser($user->id), 'segunda chamada não cria nada');
        $this->assertEquals(1, Task::where('parent_task_id', $task->id)->count());
    }

    public function test_roll_skips_fully_deleted_series(): void
    {
        $user = User::factory()->create();
        $task = $this->dailyTask($user, ['due_date' => now()->subDays(2)]);
        $task->delete();

        $created = app(RollRecurringTaskOccurrencesAction::class)->executeForUser($user->id);

        $this->assertEquals(0, $created, 'série toda deletada = rotina encerrada');
    }

    public function test_roll_does_not_resurrect_deleted_todays_occurrence(): void
    {
        $user = User::factory()->create();
        $root = $this->dailyTask($user, [
            'due_date' => now()->subDay(),
            'status' => 'completed',
            'completed_at' => now()->subDay(),
        ]);
        $today = $this->dailyTask($user, [
            'parent_task_id' => $root->id,
            'due_date' => now(),
        ]);
        $today->delete(); // usuário pulou o dia de propósito

        $created = app(RollRecurringTaskOccurrencesAction::class)->executeForUser($user->id);

        $this->assertEquals(0, $created);
        $this->assertEquals(1, Task::where('parent_task_id', $root->id)->withTrashed()->count());
    }

    public function test_completing_overdue_does_not_duplicate_already_materialized_today(): void
    {
        $user = User::factory()->create();
        $root = $this->dailyTask($user, ['due_date' => now()->subDay()->setTime(8, 0), 'has_due_time' => true]);
        $this->dailyTask($user, [
            'parent_task_id' => $root->id,
            'due_date' => now()->setTime(8, 0),
            'has_due_time' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/tasks/{$root->id}/complete")
            ->assertOk();

        $this->assertEquals(
            1,
            Task::where('parent_task_id', $root->id)->whereDate('due_date', today())->count(),
            'concluir a atrasada não pode duplicar a ocorrência de hoje'
        );
    }

    // ── weekdays (regressão do enum) ──────────────────────────────────────────

    public function test_weekdays_recurrence_is_accepted_by_the_api(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/tasks', [
                'title' => 'Ir ao trabalho',
                'due_date' => now()->toDateString(),
                'recurrence_type' => 'weekdays',
            ])
            ->assertCreated()
            ->assertJsonPath('data.recurrence_type', 'weekdays');
    }

    // ── Sem hora ≠ meia-noite ─────────────────────────────────────────────────

    public function test_task_without_time_is_not_overdue_during_its_due_day(): void
    {
        $user = User::factory()->create();
        $noTimeToday = Task::factory()->create([
            'user_id' => $user->id,
            'due_date' => today(),
            'has_due_time' => false,
        ]);
        $noTimeYesterday = Task::factory()->create([
            'user_id' => $user->id,
            'due_date' => today()->subDay(),
            'has_due_time' => false,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/tasks?period=overdue')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertFalse($ids->contains($noTimeToday->id), 'sem hora só atrasa quando o DIA passa');
        $this->assertTrue($ids->contains($noTimeYesterday->id));
    }

    public function test_task_with_explicit_midnight_time_keeps_its_time(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/tasks', [
                'title' => 'Virada',
                'due_date' => '2026-12-31',
                'due_time' => '00:00',
            ])
            ->assertCreated();

        $this->assertEquals('00:00', $response->json('data.due_time'), 'meia-noite explícita não é "sem hora"');
    }
}
