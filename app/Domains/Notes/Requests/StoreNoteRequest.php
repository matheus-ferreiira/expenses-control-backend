<?php

namespace App\Domains\Notes\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StoreNoteRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string'],
            'is_pinned' => ['nullable', 'boolean'],
            'is_favorite' => ['nullable', 'boolean'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['uuid', Rule::exists('note_tags', 'id')->where('user_id', $this->user()->id)],
        ];
    }
}
