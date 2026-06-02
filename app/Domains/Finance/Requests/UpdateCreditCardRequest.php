<?php

namespace App\Domains\Finance\Requests;

use App\Http\Requests\BaseFormRequest;

class UpdateCreditCardRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'limit_amount' => ['nullable', 'numeric', 'min:0'],
            'closing_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'due_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'bank_account_id' => ['nullable', 'string', 'uuid'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
