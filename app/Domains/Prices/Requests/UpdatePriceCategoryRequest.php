<?php

namespace App\Domains\Prices\Requests;

use App\Http\Requests\BaseFormRequest;

class UpdatePriceCategoryRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:50'],
        ];
    }
}
