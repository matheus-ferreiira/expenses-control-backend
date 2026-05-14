<?php

namespace App\Domains\Notes\Requests;

use App\Http\Requests\BaseFormRequest;

class StoreNoteTagRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:20'],
        ];
    }
}
