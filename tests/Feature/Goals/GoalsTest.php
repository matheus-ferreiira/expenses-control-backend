<?php

namespace Tests\Feature\Goals;

use App\Domains\Goals\Enums\GoalStatus;
use App\Domains\Goals\Models\Goal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalsTest extends TestCase
{
    use RefreshDatabase;

    // ── CRUD básico ───────────────────────────────────────────────────────────

    public function test_user_can_create_a_goal(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/goals', [
                'type' => 'financial',
                'title' => 'Save for vacation',
                'target_amount' => 5000,
                'target_date' => now()->addMonths(6)->toDateString(),
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('goals', [
            'user_id' => $user->id,
            'title' => 'Save for vacation',
        ]);
    }

    public function test_goal_resource_exposes_progress_percentage(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->create([
            'user_id' => $user->id,
            'target_amount' => 1000.00,
            'current_amount' => 250.00,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/goals/{$goal->id}")
            ->assertOk();

        $this->assertEquals(25.0, $response->json('data.progress_percentage'));
    }

    // ── updateProgress — lógica crítica do bug corrigido ─────────────────────

    public function test_update_progress_persists_current_amount(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->create([
            'user_id' => $user->id,
            'target_amount' => 1000.00,
            'current_amount' => 0.00,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/goals/{$goal->id}/progress", [
                'current_amount' => 400,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('goals', [
            'id' => $goal->id,
            'current_amount' => 400.00,
        ]);
        $this->assertEquals(400.0, (float) $response->json('data.current_amount'));
    }

    public function test_reaching_target_amount_marks_goal_as_completed(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->create([
            'user_id' => $user->id,
            'target_amount' => 1000.00,
            'current_amount' => 0.00,
            'status' => GoalStatus::Active,
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/goals/{$goal->id}/progress", [
                'current_amount' => 1000,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('goals', [
            'id' => $goal->id,
            'status' => 'completed',
        ]);
        $this->assertNotNull(Goal::find($goal->id)->completed_at);
    }

    public function test_exceeding_target_clamps_progress_to_not_exceed_100_percent(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->create([
            'user_id' => $user->id,
            'target_amount' => 500.00,
            'current_amount' => 0.00,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/goals/{$goal->id}/progress", [
                'current_amount' => 999,
            ])
            ->assertOk();

        $this->assertEquals(100.0, $response->json('data.progress_percentage'));
    }

    // ── user isolation ────────────────────────────────────────────────────────

    public function test_user_can_only_see_their_own_goals(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Goal::factory()->count(2)->create(['user_id' => $user->id]);
        Goal::factory()->count(4)->create(['user_id' => $other->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/goals')
            ->assertOk();

        $this->assertSame(2, $response->json('meta.total'));
    }

    public function test_user_cannot_view_another_users_goal(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $goal = Goal::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/goals/{$goal->id}")
            ->assertForbidden();
    }

    public function test_user_cannot_update_progress_on_another_users_goal(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $goal = Goal::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/goals/{$goal->id}/progress", [
                'current_amount' => 500,
            ])
            ->assertForbidden();
    }

    public function test_user_cannot_delete_another_users_goal(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $goal = Goal::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/goals/{$goal->id}")
            ->assertForbidden();
    }

    // ── validação de input ────────────────────────────────────────────────────

    public function test_update_progress_requires_numeric_current_amount(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/goals/{$goal->id}/progress", [
                'current_amount' => 'not-a-number',
            ])
            ->assertUnprocessable();
    }
}
