<?php

namespace Tests\Feature\Notes;

use App\Domains\Notes\Models\Note;
use App\Domains\Notes\Models\NoteTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotesTest extends TestCase
{
    use RefreshDatabase;

    // ── tag ownership security on create ─────────────────────────────────────

    public function test_user_cannot_attach_another_users_tag_on_create(): void
    {
        $user = User::factory()->create();
        $attacker = User::factory()->create();
        $foreignTag = NoteTag::factory()->create(['user_id' => $attacker->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/notes', [
                'title' => 'My note',
                'content' => 'Content',
                'tag_ids' => [$foreignTag->id],
            ])
            ->assertUnprocessable();
    }

    public function test_user_can_attach_own_tags_on_create(): void
    {
        $user = User::factory()->create();
        $tag = NoteTag::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/notes', [
                'title' => 'My note',
                'content' => 'Content',
                'tag_ids' => [$tag->id],
            ])
            ->assertCreated();

        $note = Note::where('user_id', $user->id)->first();
        $this->assertSame(1, $note->tags()->count());
        $this->assertEquals($tag->id, $note->tags->first()->id);
    }

    // ── tag ownership security on update ─────────────────────────────────────

    public function test_user_cannot_attach_another_users_tag_on_update(): void
    {
        $user = User::factory()->create();
        $attacker = User::factory()->create();
        $note = Note::factory()->create(['user_id' => $user->id]);
        $foreignTag = NoteTag::factory()->create(['user_id' => $attacker->id]);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/notes/{$note->id}", [
                'title' => 'Updated',
                'content' => 'Content',
                'tag_ids' => [$foreignTag->id],
            ])
            ->assertUnprocessable();
    }

    public function test_updating_note_with_own_tags_attaches_them(): void
    {
        $user = User::factory()->create();
        $note = Note::factory()->create(['user_id' => $user->id]);
        $tag = NoteTag::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/notes/{$note->id}", [
                'title' => 'Updated',
                'content' => 'Content',
                'tag_ids' => [$tag->id],
            ])
            ->assertOk();

        $this->assertSame(1, $note->fresh()->tags()->count());
    }

    // ── note tag partial update (UpdateNoteTagRequest uses sometimes) ─────────

    public function test_updating_note_tag_with_only_name_does_not_require_color(): void
    {
        $user = User::factory()->create();
        $tag = NoteTag::factory()->create(['user_id' => $user->id, 'name' => 'old-name', 'color' => '#ffffff']);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/note-tags/{$tag->id}", ['name' => 'new-name'])
            ->assertOk();

        $this->assertDatabaseHas('note_tags', [
            'id' => $tag->id,
            'name' => 'new-name',
            'color' => '#ffffff',
        ]);
    }

    // ── user isolation ────────────────────────────────────────────────────────

    public function test_user_can_only_see_their_own_notes(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Note::factory()->count(3)->create(['user_id' => $user->id]);
        Note::factory()->count(5)->create(['user_id' => $other->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/notes')
            ->assertOk();

        $userIds = collect($response->json('data'))->pluck('user_id')->unique()->values()->all();
        $this->assertSame([$user->id], $userIds);
    }

    public function test_user_cannot_view_another_users_note(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $note = Note::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/notes/{$note->id}")
            ->assertForbidden();
    }

    public function test_user_cannot_delete_another_users_note(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $note = Note::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/notes/{$note->id}")
            ->assertForbidden();
    }

    // ── archive / unarchive ───────────────────────────────────────────────────

    public function test_archiving_note_sets_archived_at(): void
    {
        $user = User::factory()->create();
        $note = Note::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/notes/{$note->id}/archive")
            ->assertOk();

        $this->assertNotNull($note->fresh()->archived_at);
    }

    public function test_unarchiving_note_clears_archived_at(): void
    {
        $user = User::factory()->create();
        $note = Note::factory()->archived()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/notes/{$note->id}/unarchive")
            ->assertOk();

        $this->assertNull($note->fresh()->archived_at);
    }
}
