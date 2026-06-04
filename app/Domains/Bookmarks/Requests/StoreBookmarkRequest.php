<?php

namespace App\Domains\Bookmarks\Requests;

use App\Http\Requests\BaseFormRequest;

class StoreBookmarkRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', 'url', 'max:2048'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'is_favorite' => ['sometimes', 'boolean'],
        ];
    }
}
