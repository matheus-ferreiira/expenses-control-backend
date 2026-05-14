<?php

namespace Tests\Feature\Tasks;

use App\Domains\Tasks\Enums\TaskPriority;
use App\Domains\Tasks\Models\Subtask;
use App\Domains\Tasks\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TasksTest extends TestCase
{
    use RefreshDatabase;

    // ── SQL Injection Prevention ──────────────────────────────────────────────

    public function test_sort_by_injection_attempt_is_rejected_with_validation_error(): void
    {
        $user = User::factory()->create();
        Task::factory()->count(3)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/tasks?sort_by=user_id;DROP TABLE tasks--&sort_direction=asc');

        // Validation whitelist rejects invalid sort_by before it reaches the query
        $response->assertUnprocessable();
        $this->assertDatabaseCount('tasks', 3);
    }

    public function test_sort_by_non_whitelisted_column_is_rejected(): void
    {
        $user = User::factory()->create();
        Task::factory()->count(3)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/tasks?sort_by=malicious_column');

        $response->assertUnprocessable();
    }

    public function test_sort_by_whitelisted_columns_are_accepted(): void
    {
        $user = User::factory()->create();
        Task::factory()->count(3)->create(['user_id' => $user->id]);

        foreach (['position', 'due_date', 'created_at', 'priority', 'title'] as $col) {
            $this->actingAs($user, 'sanctum')
                ->getJson("/api/v1/tasks?sort_by={$col}")
                ->assertOk();
        }
    }

    // ── Archived Filter ───────────────────────────────────────────────────────

    public function test_default_task_list_excludes_archived_tasks(): void
    {
        $user = User::factory()->create();
        $active = Task::factory()->create(['user_id' => $user->id, 'is_archived' => false]);
        $archived = Task::factory()->archived()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/tasks');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($active->id));
        $this->assertFalse($ids->contains($archived->id));
    }

    public function test_archived_boolean_filter_returns_only_archived_tasks(): void
    {
        $user = User::factory()->create();
        $active = Task::factory()->create(['user_id' => $user->id, 'is_archived' => false]);
        $archived = Task::factory()->archived()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/tasks?archived=1');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($archived->id));
        $this->assertFalse($ids->contains($active->id));
    }

    // ── Priority Enum Default ─────────────────────────────────────────────────

    public function test_task_is_created_with_normal_priority_by_default(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/tasks', ['title' => 'Test task']);

        $response->assertCreated();
        $this->assertSame('normal', $response->json('data.priority'));
        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'title' => 'Test task',
            'priority' => TaskPriority::Normal->value,
        ]);
    }

    // ── User Isolation ────────────────────────────────────────────────────────

    public function test_user_can_only_see_their_own_tasks(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        Task::factory()->count(2)->create(['user_id' => $userA->id]);
        Task::factory()->count(3)->create(['user_id' => $userB->id]);

        $response = $this->actingAs($userA, 'sanctum')
            ->getJson('/api/v1/tasks');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    // ── Subtask Ownership ─────────────────────────────────────────────────────

    public function test_user_cannot_update_subtask_belonging_to_another_users_task(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        $task = Task::factory()->create(['user_id' => $owner->id]);
        $subtask = Subtask::factory()->create(['task_id' => $task->id, 'title' => 'original']);

        // Policy rejects at task level (403) before ownership check
        $this->actingAs($attacker, 'sanctum')
            ->putJson("/api/v1/tasks/{$task->id}/subtasks/{$subtask->id}", [
                'title' => 'hacked',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('subtasks', ['id' => $subtask->id, 'title' => 'original']);
    }

    public function test_subtask_from_different_task_returns_404(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['user_id' => $user->id]);
        $otherTask = Task::factory()->create(['user_id' => $user->id]);
        $subtask = Subtask::factory()->create(['task_id' => $otherTask->id, 'title' => 'original']);

        // Subtask belongs to a different task owned by the same user → 404
        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/tasks/{$task->id}/subtasks/{$subtask->id}", [
                'title' => 'tampered',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('subtasks', ['id' => $subtask->id, 'title' => 'original']);
    }

    public function test_user_cannot_delete_subtask_belonging_to_another_users_task(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        $task = Task::factory()->create(['user_id' => $owner->id]);
        $subtask = Subtask::factory()->create(['task_id' => $task->id]);

        // Policy rejects at task level (403)
        $this->actingAs($attacker, 'sanctum')
            ->deleteJson("/api/v1/tasks/{$task->id}/subtasks/{$subtask->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('subtasks', ['id' => $subtask->id]);
    }
}
