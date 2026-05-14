<?php

namespace App\Domains\Notes\Requests;

use App\Http\Requests\BaseFormRequest;

class NoteFilterRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'tag_id' => ['nullable', 'uuid'],
            'is_pinned' => ['nullable', 'boolean'],
            'is_favorite' => ['nullable', 'boolean'],
            'archived' => ['nullable', 'boolean'],
        ];
    }
}
