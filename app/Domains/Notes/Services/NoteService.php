<?php

namespace App\Domains\Notes\Services;

use App\Domains\Notes\Actions\CreateNoteAction;
use App\Domains\Notes\Actions\UpdateNoteAction;
use App\Domains\Notes\DTOs\NoteDTO;
use App\Domains\Notes\Models\Note;
use App\Domains\Notes\Models\NoteTag;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class NoteService
{
    public function __construct(
        private readonly CreateNoteAction $createNote,
        private readonly UpdateNoteAction $updateNote,
    ) {}

    public function list(User $user, array $filters = []): Collection
    {
        $query = Note::forUser($user->id)->with('tags');

        if (isset($filters['archived']) && $filters['archived']) {
            $query->archived();
        } else {
            $query->active();
        }

        if (! empty($filters['is_pinned'])) {
            $query->pinned();
        }

        if (! empty($filters['is_favorite'])) {
            $query->favorite();
        }

        if (! empty($filters['tag_id'])) {
            $query->whereHas('tags', fn ($q) => $q->where('note_tags.id', $filters['tag_id']));
        }

        if (! empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function ($q) use ($term) {
                $q->where('title', 'ilike', "%{$term}%")
                    ->orWhere('content', 'ilike', "%{$term}%");
            });
        }

        return $query->orderByDesc('is_pinned')->orderByDesc('updated_at')->get();
    }

    public function create(User $user, NoteDTO $dto): Note
    {
        return $this->createNote->execute($user, $dto);
    }

    public function update(Note $note, NoteDTO $dto): Note
    {
        return $this->updateNote->execute($note, $dto);
    }

    public function delete(Note $note): void
    {
        $note->delete();
    }

    public function pin(Note $note, bool $pin): Note
    {
        $note->update(['is_pinned' => $pin]);

        return $note->load('tags');
    }

    public function favorite(Note $note, bool $favorite): Note
    {
        $note->update(['is_favorite' => $favorite]);

        return $note->load('tags');
    }

    public function archive(Note $note, bool $archive): Note
    {
        $note->update(['archived_at' => $archive ? now() : null]);

        return $note->load('tags');
    }

    public function touch(Note $note): Note
    {
        $note->update(['last_viewed_at' => now()]);

        return $note;
    }

    // Tags

    public function listTags(User $user): Collection
    {
        return NoteTag::forUser($user->id)->orderBy('name')->get();
    }

    public function createTag(User $user, array $data): NoteTag
    {
        return NoteTag::create([...$data, 'user_id' => $user->id]);
    }

    public function updateTag(NoteTag $tag, array $data): NoteTag
    {
        $tag->update($data);

        return $tag;
    }

    public function deleteTag(NoteTag $tag): void
    {
        $tag->delete();
    }
}
