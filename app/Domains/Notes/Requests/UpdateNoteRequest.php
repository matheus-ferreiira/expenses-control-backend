<?php

namespace App\Domains\Notes\Requests;

use App\Http\Requests\BaseFormRequest;

class UpdateNoteRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'nullable', 'string', 'max:500'],
            'content' => ['sometimes', 'nullable', 'string'],
            'is_pinned' => ['sometimes', 'boolean'],
            'is_favorite' => ['sometimes', 'boolean'],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['uuid', 'exists:note_tags,id'],
        ];
    }
}
