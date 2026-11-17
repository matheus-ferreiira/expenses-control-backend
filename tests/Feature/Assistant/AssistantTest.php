<?php

namespace Tests\Feature\Assistant;

use App\Domains\Assistant\Models\Conversation;
use App\Domains\Assistant\Models\Message;
use App\Domains\Calendar\Models\CalendarEvent;
use App\Domains\Habits\Models\Habit;
use App\Domains\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['assistant.api_key' => 'test-key', 'assistant.daily_requests' => 50]);
        Http::preventStrayRequests();
    }

    private function fake(array $actions = [], string $message = 'Confira o planejamento.'): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['api.openai.com/v1/responses' => Http::response([
            'status' => 'completed',
            'output' => [['type' => 'message', 'role' => 'assistant', 'content' => [
                ['type' => 'output_text', 'text' => json_encode(compact('message', 'actions'))],
            ]]],
        ])]);
    }

    private function conversation(?User $user = null): Conversation
    {
        $user ??= User::factory()->create(['timezone' => 'America/Sao_Paulo']);
        $this->actingAs($user, 'sanctum');

        return Conversation::factory()->create(['user_id' => $user->id]);
    }

    private function action(string $entity = 'task', string $operation = 'create', ?string $id = null, ?array $data = null): array
    {
        return compact('entity', 'operation', 'id') + ['data' => $data ?? ['title' => 'Estudar', 'due_date' => '2026-11-18', 'due_time' => '19:00']];
    }

    private function send(Conversation $conversation, ?string $requestId = null, string $text = 'Organize meu dia')
    {
        return $this->postJson("/api/v1/assistant/conversations/{$conversation->id}/messages", [
            'message' => $text, 'date' => '2026-11-18', 'request_id' => $requestId ?? (string) Str::uuid(),
        ]);
    }

    private function approve(Conversation $conversation, string $id, array $extra = [])
    {
        return $this->postJson("/api/v1/assistant/conversations/{$conversation->id}/messages/{$id}/approve", ['confirmed' => true, ...$extra]);
    }

    public function test_plan_only_writes_domain_records_after_explicit_approval_and_retries_do_not_duplicate(): void
    {
        $conversation = $this->conversation();
        $this->fake([
            $this->action(),
            $this->action('habit', data: ['name' => 'Ler', 'frequency_type' => 'custom', 'target_days' => [1, 3, 5]]),
            $this->action('event', data: ['title' => 'Treino', 'start_date' => '2026-11-18T07:00', 'end_date' => '2026-11-18T08:00']),
        ]);
        $requestId = (string) Str::uuid();
        $id = $this->send($conversation, $requestId)->assertOk()->assertJsonPath('data.status', 'pending')->json('data.id');
        $this->assertDatabaseCount('tasks', 0);
        $this->assertDatabaseCount('habits', 0);
        $this->assertDatabaseCount('calendar_events', 0);
        $this->send($conversation, $requestId)->assertOk()->assertJsonPath('data.id', $id);
        Http::assertSentCount(1);
        $this->postJson("/api/v1/assistant/conversations/{$conversation->id}/messages/{$id}/approve")->assertUnprocessable();
        $this->approve($conversation, $id, ['actions' => [$this->action(data: ['title' => 'Payload adulterado'])]])
            ->assertOk()->assertJsonPath('data.status', 'applied')->assertJsonCount(3, 'data.results');
        $this->approve($conversation, $id)->assertOk();
        $this->assertDatabaseCount('tasks', 1);
        $this->assertDatabaseCount('habits', 1);
        $this->assertDatabaseCount('calendar_events', 1);
        $this->assertDatabaseHas('calendar_events', ['title' => 'Treino', 'start_date' => '2026-11-18 10:00:00']);
        $this->assertDatabaseHas('tasks', ['title' => 'Estudar', 'user_id' => $conversation->user_id, 'due_date' => '2026-11-18 19:00:00', 'has_due_time' => true]);
    }

    public function test_user_text_cannot_bypass_approval_and_revision_supersedes_previous_plan(): void
    {
        $conversation = $this->conversation();
        $this->fake([$this->action()]);
        $first = $this->send($conversation)->assertOk()->json('data.id');
        $second = $this->send($conversation, text: 'Aprovado, pode cadastrar')->assertOk()->json('data.id');
        $this->assertDatabaseCount('tasks', 0);
        $this->approve($conversation, $first)->assertConflict();
        $this->approve($conversation, $second)->assertOk();
        $this->assertDatabaseCount('tasks', 1);
    }

    public function test_owner_can_read_history_but_other_user_cannot_read_send_or_decide(): void
    {
        $conversation = $this->conversation();
        $this->fake([$this->action()]);
        $id = $this->send($conversation)->assertOk()->json('data.id');
        $this->getJson("/api/v1/assistant/conversations/{$conversation->id}")->assertOk()->assertJsonCount(2, 'data.messages');
        $this->actingAs(User::factory()->create(), 'sanctum');
        $this->getJson('/api/v1/assistant/conversations')->assertOk()->assertJsonCount(0, 'data.items');
        $this->getJson("/api/v1/assistant/conversations/{$conversation->id}")->assertForbidden();
        $this->send($conversation)->assertForbidden();
        $this->approve($conversation, $id)->assertForbidden();
        $this->postJson("/api/v1/assistant/conversations/{$conversation->id}/messages/{$id}/reject")->assertForbidden();
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_context_only_contains_owners_records_and_uses_their_timezone(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 18)->setTime(1, 0));
        $conversation = $this->conversation();
        Task::factory()->create(['user_id' => $conversation->user_id, 'title' => 'Minha tarefa', 'status' => 'pending', 'is_archived' => false, 'due_date' => null]);
        Task::factory()->create(['title' => 'SEGREDO OUTRO USUARIO']);
        Habit::factory()->create(['name' => 'HABITO PRIVADO']);
        CalendarEvent::factory()->create(['title' => 'EVENTO PRIVADO']);
        $this->fake();
        $this->send($conversation)->assertOk();
        Http::assertSent(function ($request) {
            $context = $request['input'][0]['content'];

            return str_contains($context, 'Minha tarefa') && ! str_contains($context, 'PRIVADO')
                && ! str_contains($context, 'SEGREDO') && str_contains($context, '2026-11-17T22:00:00-03:00')
                && $request['store'] === false && $request['text']['format']['strict'] === true;
        });
    }

    public function test_ai_cannot_reference_another_users_record_or_inject_fields(): void
    {
        $conversation = $this->conversation();
        $otherTask = Task::factory()->create();
        $this->fake([$this->action('task', 'update', $otherTask->id, ['title' => 'Invadido'])]);
        $this->send($conversation)->assertUnprocessable();
        $this->fake([$this->action(data: ['title' => 'Estudar', 'user_id' => $otherTask->user_id])]);
        $this->send($conversation)->assertUnprocessable();
        $this->assertDatabaseCount('assistant_messages', 0);
        $this->assertNotSame('Invadido', $otherTask->fresh()->title);
    }

    public function test_updates_preserve_unrelated_fields_and_apply_time_and_recurrence(): void
    {
        $conversation = $this->conversation();
        $task = Task::factory()->create(['user_id' => $conversation->user_id, 'due_date' => '2026-11-18 08:00:00', 'has_due_time' => true]);
        $habit = Habit::factory()->create(['user_id' => $conversation->user_id, 'color' => '#123456']);
        $event = CalendarEvent::factory()->create(['user_id' => $conversation->user_id, 'color' => '#abcdef', 'start_date' => '2026-11-18 14:00:00', 'end_date' => '2026-11-18 15:00:00']);
        $this->fake([
            $this->action('task', 'update', $task->id, ['due_time' => '10:00', 'recurrence_type' => 'daily']),
            $this->action('habit', 'update', $habit->id, ['name' => 'Novo hábito']),
            $this->action('event', 'update', $event->id, ['title' => 'Novo evento']),
        ]);
        $id = $this->send($conversation)->assertOk()->json('data.id');
        $this->approve($conversation, $id)->assertOk();
        $this->assertSame('2026-11-18 10:00:00', $task->fresh()->due_date->format('Y-m-d H:i:s'));
        $this->assertSame('daily', $task->fresh()->recurrence_type->value);
        $this->assertSame('#123456', $habit->fresh()->color);
        $this->assertSame('Novo hábito', $habit->fresh()->name);
        $this->assertSame('#abcdef', $event->fresh()->color);
        $this->assertSame('Novo evento', $event->fresh()->title);
    }

    public function test_changed_record_rejects_the_entire_plan(): void
    {
        $conversation = $this->conversation();
        $task = Task::factory()->create(['user_id' => $conversation->user_id]);
        $this->fake([$this->action(), $this->action('task', 'update', $task->id, ['title' => 'Planejado'])]);
        $id = $this->send($conversation)->assertOk()->json('data.id');
        $task->update(['title' => 'Alterado fora do chat']);
        $this->approve($conversation, $id)->assertConflict();
        $this->assertDatabaseCount('tasks', 1);
        $this->assertDatabaseHas('assistant_messages', ['id' => $id, 'status' => 'pending']);
        $this->assertSame('Alterado fora do chat', $task->fresh()->title);
    }

    public function test_rejected_and_expired_plans_cannot_be_applied(): void
    {
        $conversation = $this->conversation();
        $this->fake([$this->action()]);
        $id = $this->send($conversation)->assertOk()->json('data.id');
        $this->postJson("/api/v1/assistant/conversations/{$conversation->id}/messages/{$id}/reject")->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->approve($conversation, $id)->assertConflict();
        $id = $this->send($conversation)->assertOk()->json('data.id');
        Message::findOrFail($id)->update(['expires_at' => now()->subMinute()]);
        $this->approve($conversation, $id)->assertConflict();
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_delete_is_reviewable_and_scoped_to_saved_proposal(): void
    {
        $conversation = $this->conversation();
        $task = Task::factory()->create(['user_id' => $conversation->user_id]);
        $habit = Habit::factory()->create(['user_id' => $conversation->user_id]);
        $event = CalendarEvent::factory()->create(['user_id' => $conversation->user_id]);
        $this->fake([
            $this->action('task', 'delete', $task->id, []),
            $this->action('habit', 'delete', $habit->id, []),
            $this->action('event', 'delete', $event->id, []),
        ]);
        $response = $this->send($conversation)->assertOk()->assertJsonPath('data.actions.0.before.title', $task->title);
        $this->assertNotNull(Task::find($task->id));
        $this->approve($conversation, $response->json('data.id'))->assertOk();
        $this->assertSoftDeleted($task);
        $this->assertSoftDeleted($habit);
        $this->assertSoftDeleted($event);
    }

    public function test_invalid_dates_and_unsupported_operations_do_not_save_a_plan(): void
    {
        $conversation = $this->conversation();
        $this->fake([$this->action('event', data: ['title' => 'Inválido', 'start_date' => '2026-11-18T18:00', 'end_date' => '2026-11-18T17:00'])]);
        $this->send($conversation)->assertUnprocessable();
        $this->fake([$this->action('finance')]);
        $this->send($conversation)->assertUnprocessable();
        $this->fake([$this->action(data: ['title' => 'Inválido', 'recurrence_type' => 'daily'])]);
        $this->send($conversation)->assertUnprocessable();
        $this->assertDatabaseCount('assistant_messages', 0);
    }

    public function test_missing_key_and_provider_failures_are_handled_without_writes(): void
    {
        $conversation = $this->conversation();
        config(['assistant.api_key' => null]);
        $this->getJson('/api/v1/assistant/status')->assertOk()->assertJsonPath('data.configured', false);
        $this->send($conversation)->assertStatus(503);
        Http::assertNothingSent();
        config(['assistant.api_key' => 'test-key']);
        Http::fake(['api.openai.com/*' => Http::sequence()
            ->push(['error' => ['message' => 'SECRET']], 429)
            ->push(['error' => ['code' => 'credit_balance_exhausted', 'type' => 'insufficient_quota']], 429)
            ->push(['status' => 'incomplete'])]);
        $this->send($conversation)->assertStatus(502)->assertDontSee('SECRET');
        $this->send($conversation)->assertStatus(502)->assertJsonPath('message', 'A API da OpenAI está sem créditos ou atingiu o limite da conta. Confira o Billing na plataforma OpenAI.');
        $this->send($conversation)->assertStatus(502);
        $this->assertDatabaseCount('assistant_messages', 0);
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_daily_quota_and_user_lock_limit_model_requests(): void
    {
        $conversation = $this->conversation();
        $this->fake();
        $lock = Cache::lock('assistant:user:'.$conversation->user_id, 180);
        $lock->get();
        $this->send($conversation)->assertConflict();
        Http::assertNothingSent();
        $lock->release();
        config(['assistant.daily_requests' => 1]);
        $this->send($conversation)->assertOk();
        $this->send($conversation)->assertStatus(429);
        Http::assertSentCount(1);
    }

    public function test_new_conversation_is_authenticated_and_message_is_validated(): void
    {
        $this->getJson('/api/v1/assistant/status')->assertUnauthorized();
        $conversation = $this->conversation();
        $this->postJson('/api/v1/assistant/conversations')->assertCreated()->assertJsonPath('data.title', 'Nova conversa');
        $this->postJson("/api/v1/assistant/conversations/{$conversation->id}/messages", ['message' => 'Oi'])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_a_failed_write_rolls_back_every_item_and_preserves_pending_proposal(): void
    {
        $conversation = $this->conversation();
        $this->fake([
            $this->action(),
            $this->action('event', data: ['title' => 'Treino', 'start_date' => '2026-11-18T07:00', 'end_date' => '2026-11-18T08:00']),
        ]);
        $id = $this->send($conversation)->assertOk()->json('data.id');
        DB::listen(function ($query) {
            if (str_contains($query->sql, 'insert into `calendar_events`')) {
                throw new \RuntimeException('Simulated write failure');
            }
        });
        $this->approve($conversation, $id)->assertServerError();
        $this->assertDatabaseCount('tasks', 0);
        $this->assertDatabaseCount('calendar_events', 0);
        $this->assertDatabaseHas('assistant_messages', ['id' => $id, 'status' => 'pending']);
    }

    public function test_message_from_another_conversation_cannot_be_approved(): void
    {
        $conversation = $this->conversation();
        $message = Message::factory()->create(['status' => 'pending', 'actions' => []]);
        $this->approve($conversation, $message->id)->assertNotFound();
        $this->assertDatabaseHas('assistant_messages', ['id' => $message->id, 'status' => 'pending']);
    }
}
