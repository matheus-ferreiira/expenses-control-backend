<?php

namespace App\Domains\Notes\Actions;

use App\Domains\Notes\DTOs\NoteDTO;
use App\Domains\Notes\Models\Note;
use App\Domains\Notes\Models\NoteTag;

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

        $ownedTagIds = empty($dto->tagIds) ? [] : NoteTag::where('user_id', $note->user_id)
            ->whereIn('id', $dto->tagIds)
            ->pluck('id')
            ->all();
        $note->tags()->sync($ownedTagIds);

        return $note->load('tags');
    }
}
