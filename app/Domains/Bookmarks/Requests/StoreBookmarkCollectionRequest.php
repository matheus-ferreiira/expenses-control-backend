<?php

namespace App\Domains\Bookmarks\Requests;

use App\Http\Requests\BaseFormRequest;

class StoreBookmarkCollectionRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:50'],
            'color' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }
}
