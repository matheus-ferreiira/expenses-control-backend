<?php

namespace App\Domains\Purchases\Requests;

use App\Http\Requests\BaseFormRequest;

class FinishShoppingSessionRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'total' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'bank_account_id' => ['sometimes', 'nullable', 'string'],
            'credit_card_id' => ['sometimes', 'nullable', 'string'],
            'category_id' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
