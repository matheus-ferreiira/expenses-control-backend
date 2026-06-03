<?php

namespace App\Domains\Purchases\Requests;

use App\Http\Requests\BaseFormRequest;

class UpdateShoppingItemRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_bought' => ['sometimes', 'boolean'],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999.99'],
        ];
    }
}
