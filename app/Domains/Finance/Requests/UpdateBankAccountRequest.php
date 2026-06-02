<?php

namespace App\Domains\Finance\Requests;

use App\Domains\Finance\Enums\AccountType;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateBankAccountRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['nullable', new Enum(AccountType::class)],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'balance' => ['sometimes', 'numeric', 'min:-9999999', 'max:9999999999'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
