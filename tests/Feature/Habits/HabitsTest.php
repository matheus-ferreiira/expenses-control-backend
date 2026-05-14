<?php

namespace Tests\Feature\Habits;

use App\Domains\Habits\Models\Habit;
use App\Domains\Habits\Models\HabitLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HabitsTest extends TestCase
{
    use RefreshDatabase;

    // ── log() returns HabitResource (not HabitLog raw) ───────────────────────

    public function test_log_endpoint_returns_habit_resource_with_logs(): void
    {
        $user = User::factory()->create();
        $habit = Habit::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/habits/{$habit->id}/log", [
                'completed_date' => today()->toDateString(),
            ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => ['id', 'name', 'frequency', 'logs'],
        ]);
        $this->assertEquals($habit->id, $response->json('data.id'));
        $this->assertIsArray($response->json('data.logs'));
    }

    // ── double-log same date creates only one entry (updateOrCreate) ──────────

    public function test_logging_same_date_twice_creates_only_one_log(): void
    {
        $user = User::factory()->create();
        $habit = Habit::factory()->create(['user_id' => $user->id]);
        $date = today()->toDateString();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/habits/{$habit->id}/log", ['completed_date' => $date])
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/habits/{$habit->id}/log", ['completed_date' => $date])
            ->assertOk();

        $this->assertSame(
            1,
            HabitLog::where('habit_id', $habit->id)->whereDate('completed_date', $date)->count()
        );
    }

    // ── frequency_type persisted correctly ────────────────────────────────────

    public function test_creating_habit_persists_frequency_type(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/habits', [
                'name' => 'Read books',
                'frequency_type' => 'weekly',
                'target_frequency' => 3,
                'color' => '#6366f1',
                'icon' => 'book',
                'start_date' => today()->toDateString(),
            ]);

        $response->assertCreated();
        $this->assertEquals('weekly', $response->json('data.frequency'));
        $this->assertDatabaseHas('habits', [
            'user_id' => $user->id,
            'name' => 'Read books',
            'frequency_type' => 'weekly',
        ]);
    }

    // ── archive / unarchive ───────────────────────────────────────────────────

    public function test_archiving_habit_excludes_it_from_default_list(): void
    {
        $user = User::factory()->create();
        $habit = Habit::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/habits/{$habit->id}/archive")
            ->assertOk();

        $this->assertNotNull(Habit::find($habit->id)->archived_at);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/habits')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertNotContains($habit->id, $ids->all());
    }

    public function test_archived_habits_appear_with_archived_filter(): void
    {
        $user = User::factory()->create();
        $archived = Habit::factory()->archived()->create(['user_id' => $user->id]);
        Habit::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/habits?archived=1')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($archived->id, $ids);
    }

    public function test_unarchiving_habit_clears_archived_at(): void
    {
        $user = User::factory()->create();
        $habit = Habit::factory()->archived()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/habits/{$habit->id}/unarchive")
            ->assertOk();

        $this->assertNull(Habit::find($habit->id)->archived_at);
    }

    // ── user isolation ────────────────────────────────────────────────────────

    public function test_user_can_only_see_their_own_habits(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Habit::factory()->count(2)->create(['user_id' => $user->id]);
        Habit::factory()->count(3)->create(['user_id' => $other->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/habits')
            ->assertOk();

        $this->assertSame(2, $response->json('meta.total'));
    }

    public function test_user_cannot_log_another_users_habit(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $habit = Habit::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/habits/{$habit->id}/log", [
                'completed_date' => today()->toDateString(),
            ])
            ->assertForbidden();
    }

    public function test_user_cannot_delete_another_users_habit(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $habit = Habit::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/habits/{$habit->id}")
            ->assertForbidden();
    }

    // ── unlog removes the correct log ─────────────────────────────────────────

    public function test_unlog_removes_log_for_given_date(): void
    {
        $user = User::factory()->create();
        $habit = Habit::factory()->create(['user_id' => $user->id]);
        $date = today()->toDateString();

        HabitLog::factory()->create(['habit_id' => $habit->id, 'completed_date' => $date]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/habits/{$habit->id}/log", ['date' => $date])
            ->assertOk();

        $this->assertSame(
            0,
            HabitLog::where('habit_id', $habit->id)->whereDate('completed_date', $date)->count()
        );
    }
}
