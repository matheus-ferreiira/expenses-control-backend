<?php

namespace Tests\Feature\Tasks;

use App\Domains\Tasks\Models\TaskTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTagTest extends TestCase
{
    use RefreshDatabase;

    // ── CRUD ─────────────────────────────────────────────────────────────────

    public function test_user_can_create_task_tag(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/task-tags', [
                'name' => 'urgent',
                'color' => '#FF4D4D',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('task_tags', [
            'user_id' => $user->id,
            'name' => 'urgent',
        ]);
    }

    public function test_user_can_list_own_task_tags(): void
    {
        $user = User::factory()->create();
        TaskTag::factory()->count(4)->create(['user_id' => $user->id]);

        $other = User::factory()->create();
        TaskTag::factory()->count(2)->create(['user_id' => $other->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/task-tags');

        $response->assertOk();
        $this->assertCount(4, $response->json('data'));
    }

    public function test_user_can_update_own_task_tag(): void
    {
        $user = User::factory()->create();
        $tag = TaskTag::factory()->create(['user_id' => $user->id, 'name' => 'old']);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/task-tags/{$tag->id}", ['name' => 'new'])
            ->assertOk();

        $this->assertDatabaseHas('task_tags', ['id' => $tag->id, 'name' => 'new']);
    }

    public function test_user_can_delete_own_task_tag(): void
    {
        $user = User::factory()->create();
        $tag = TaskTag::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/task-tags/{$tag->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('task_tags', ['id' => $tag->id]);
    }

    // ── User Isolation ────────────────────────────────────────────────────────

    public function test_user_cannot_update_another_users_task_tag(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $tag = TaskTag::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/task-tags/{$tag->id}", ['name' => 'hacked'])
            ->assertForbidden();
    }

    public function test_user_cannot_delete_another_users_task_tag(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $tag = TaskTag::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/task-tags/{$tag->id}")
            ->assertForbidden();
    }

    // ── Validation ────────────────────────────────────────────────────────────

    public function test_name_is_required_to_create_task_tag(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/task-tags', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_name_max_length_is_enforced(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/task-tags', ['name' => str_repeat('a', 101)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }
}
