<?php

namespace App\Domains\Finance\Requests;

use App\Http\Requests\BaseFormRequest;

class UpdateTransactionTagRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'color' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }
}
