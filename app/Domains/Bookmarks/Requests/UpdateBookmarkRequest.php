<?php

namespace App\Domains\Bookmarks\Requests;

use App\Http\Requests\BaseFormRequest;

class UpdateBookmarkRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'url' => ['sometimes', 'required', 'string', 'url', 'max:2048'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'is_favorite' => ['sometimes', 'boolean'],
            'bookmark_collection_id' => ['sometimes', 'required', 'uuid', 'exists:bookmark_collections,id'],
        ];
    }
}
