<?php

namespace Tests\Feature\Calendar;

use App\Domains\Calendar\Models\CalendarEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use RefreshDatabase;

    // ── index requires start_date + end_date (bug fix: validated array) ───────

    public function test_index_requires_start_date_and_end_date(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/calendar')
            ->assertUnprocessable();
    }

    public function test_index_returns_events_in_range(): void
    {
        $user = User::factory()->create();

        $inside = CalendarEvent::factory()->create([
            'user_id' => $user->id,
            'start_date' => now()->addDays(5),
            'end_date' => now()->addDays(5)->addHour(),
        ]);

        CalendarEvent::factory()->create([
            'user_id' => $user->id,
            'start_date' => now()->addDays(40),
            'end_date' => now()->addDays(40)->addHour(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/calendar?start_date='.now()->toDateString().'&end_date='.now()->addDays(10)->toDateString())
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($inside->id, $ids);
        $this->assertCount(1, $ids);
    }

    // ── CRUD básico ───────────────────────────────────────────────────────────

    public function test_user_can_create_event(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/calendar', [
                'title' => 'Team meeting',
                'start_date' => now()->addDay()->toISOString(),
                'end_date' => now()->addDay()->addHour()->toISOString(),
                'is_all_day' => false,
                'color' => '#6366f1',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('calendar_events', [
            'user_id' => $user->id,
            'title' => 'Team meeting',
        ]);
    }

    public function test_user_can_update_event(): void
    {
        $user = User::factory()->create();
        $event = CalendarEvent::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/calendar/{$event->id}", [
                'title' => 'Updated title',
                'start_date' => $event->start_date->toISOString(),
                'end_date' => $event->end_date->toISOString(),
                'is_all_day' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated title');
    }

    public function test_user_can_delete_event(): void
    {
        $user = User::factory()->create();
        $event = CalendarEvent::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/calendar/{$event->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('calendar_events', ['id' => $event->id]);
    }

    // ── user isolation ────────────────────────────────────────────────────────

    public function test_index_only_returns_events_of_authenticated_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        CalendarEvent::factory()->count(2)->create([
            'user_id' => $user->id,
            'start_date' => now()->addDays(2),
            'end_date' => now()->addDays(2)->addHour(),
        ]);

        CalendarEvent::factory()->count(3)->create([
            'user_id' => $other->id,
            'start_date' => now()->addDays(2),
            'end_date' => now()->addDays(2)->addHour(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/calendar?start_date='.now()->toDateString().'&end_date='.now()->addDays(10)->toDateString())
            ->assertOk();

        $this->assertCount(2, $response->json('data'));
    }

    public function test_user_cannot_view_another_users_event(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $event = CalendarEvent::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/calendar/{$event->id}")
            ->assertForbidden();
    }

    public function test_user_cannot_update_another_users_event(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $event = CalendarEvent::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/calendar/{$event->id}", [
                'title' => 'Hijacked',
                'start_date' => $event->start_date->toISOString(),
                'end_date' => $event->end_date->toISOString(),
                'is_all_day' => false,
            ])
            ->assertForbidden();
    }

    public function test_user_cannot_delete_another_users_event(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $event = CalendarEvent::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/calendar/{$event->id}")
            ->assertForbidden();
    }

    // ── end_date must be after start_date ─────────────────────────────────────

    public function test_index_rejects_end_date_before_start_date(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/calendar?start_date='.now()->addDays(10)->toDateString().'&end_date='.now()->toDateString())
            ->assertUnprocessable();
    }
}
