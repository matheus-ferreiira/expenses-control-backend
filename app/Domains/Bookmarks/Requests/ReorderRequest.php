<?php

namespace App\Domains\Bookmarks\Requests;

use App\Http\Requests\BaseFormRequest;

class ReorderRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'string'],
            'items.*.position' => ['required', 'integer', 'min:0'],
        ];
    }
}
