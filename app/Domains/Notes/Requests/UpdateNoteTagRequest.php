<?php

namespace App\Domains\Notes\Requests;

use App\Http\Requests\BaseFormRequest;

class UpdateNoteTagRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'color' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }
}
