<?php

namespace App\Domains\Tasks\Requests;

use App\Http\Requests\BaseFormRequest;

class StoreTaskTagRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:20'],
        ];
    }
}
