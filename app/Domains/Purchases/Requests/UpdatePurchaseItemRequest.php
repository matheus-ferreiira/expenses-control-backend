<?php

namespace App\Domains\Purchases\Requests;

use App\Http\Requests\BaseFormRequest;

class UpdatePurchaseItemRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_bought' => ['sometimes', 'boolean'],
        ];
    }
}
