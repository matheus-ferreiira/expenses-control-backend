<?php

namespace App\Domains\Notes\Actions;

use App\Domains\Notes\DTOs\NoteDTO;
use App\Domains\Notes\Models\Note;

final class UpdateNoteAction
{
    public function execute(Note $note, NoteDTO $dto): Note
    {
        $note->update([
            'title' => $dto->title,
            'content' => $dto->content,
            'is_pinned' => $dto->isPinned,
            'is_favorite' => $dto->isFavorite,
        ]);

        $note->tags()->sync($dto->tagIds);

        return $note->load('tags');
    }
}
