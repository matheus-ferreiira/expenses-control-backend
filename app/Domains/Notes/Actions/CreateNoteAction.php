<?php

namespace App\Domains\Notes\Actions;

use App\Domains\Notes\DTOs\NoteDTO;
use App\Domains\Notes\Models\Note;
use App\Domains\Notes\Models\NoteTag;
use App\Models\User;

final class CreateNoteAction
{
    public function execute(User $user, NoteDTO $dto): Note
    {
        $note = Note::create([
            'user_id' => $user->id,
            'title' => $dto->title,
            'content' => $dto->content,
            'is_pinned' => $dto->isPinned,
            'is_favorite' => $dto->isFavorite,
        ]);

        if (! empty($dto->tagIds)) {
            $ownedTagIds = NoteTag::where('user_id', $user->id)
                ->whereIn('id', $dto->tagIds)
                ->pluck('id')
                ->all();
            $note->tags()->sync($ownedTagIds);
        }

        return $note->load('tags');
    }
}
