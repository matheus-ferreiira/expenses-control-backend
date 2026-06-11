<?php

namespace Tests\Feature\Tasks;

use App\Domains\Tasks\Models\TaskList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskListTest extends TestCase
{
    use RefreshDatabase;

    // ── CRUD ─────────────────────────────────────────────────────────────────

    public function test_user_can_create_task_list(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/task-lists', [
                'name' => 'Work',
                'color' => '#00C896',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('task_lists', [
            'user_id' => $user->id,
            'name' => 'Work',
        ]);
    }

    public function test_user_can_list_own_task_lists(): void
    {
        $user = User::factory()->create();
        TaskList::factory()->count(3)->create(['user_id' => $user->id]);

        $other = User::factory()->create();
        TaskList::factory()->count(2)->create(['user_id' => $other->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/task-lists');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_user_can_update_own_task_list(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id, 'name' => 'Old']);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/task-lists/{$list->id}", ['name' => 'New'])
            ->assertOk();

        $this->assertDatabaseHas('task_lists', ['id' => $list->id, 'name' => 'New']);
    }

    public function test_user_can_delete_own_task_list(): void
    {
        $user = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/task-lists/{$list->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('task_lists', ['id' => $list->id]);
    }

    // ── User Isolation ────────────────────────────────────────────────────────

    public function test_user_cannot_update_another_users_task_list(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/task-lists/{$list->id}", ['name' => 'Hacked'])
            ->assertForbidden();
    }

    public function test_user_cannot_delete_another_users_task_list(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $list = TaskList::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/task-lists/{$list->id}")
            ->assertForbidden();
    }

    // ── Validation ────────────────────────────────────────────────────────────

    public function test_name_is_required_to_create_task_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/task-lists', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_name_max_length_is_enforced(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/task-lists', ['name' => str_repeat('a', 101)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    // ── Default Flag ──────────────────────────────────────────────────────────

    public function test_only_one_list_can_be_default_per_user(): void
    {
        $user = User::factory()->create();
        $first = TaskList::factory()->create(['user_id' => $user->id, 'is_default' => true]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/task-lists', ['name' => 'New Default', 'is_default' => true])
            ->assertCreated();

        $first->refresh();
        $this->assertFalse($first->is_default);
    }
}
