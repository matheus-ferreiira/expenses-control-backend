<?php

namespace App\Domains\Purchases\Requests;

use App\Http\Requests\BaseFormRequest;

class StoreShoppingItemRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999.99'],
        ];
    }
}
