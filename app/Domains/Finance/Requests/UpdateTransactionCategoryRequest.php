<?php

namespace App\Domains\Finance\Requests;

use App\Http\Requests\BaseFormRequest;

class UpdateTransactionCategoryRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:50'],
            'color' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }
}
