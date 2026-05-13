<?php

namespace App\Domains\Purchases\Requests;

use App\Http\Requests\BaseFormRequest;

class StorePurchaseItemRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
