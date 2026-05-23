<?php

namespace App\Domains\Finance\Requests;

use App\Http\Requests\BaseFormRequest;

class StoreTransactionTagRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:20'],
        ];
    }
}
