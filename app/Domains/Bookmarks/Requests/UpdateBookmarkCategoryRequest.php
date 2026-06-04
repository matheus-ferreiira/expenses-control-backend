<?php

namespace App\Domains\Bookmarks\Requests;

use App\Http\Requests\BaseFormRequest;

class UpdateBookmarkCategoryRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
        ];
    }
}
