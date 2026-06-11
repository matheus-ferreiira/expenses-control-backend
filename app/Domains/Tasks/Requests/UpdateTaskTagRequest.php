<?php

namespace App\Domains\Tasks\Requests;

use App\Http\Requests\BaseFormRequest;

class UpdateTaskTagRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:20'],
        ];
    }
}
