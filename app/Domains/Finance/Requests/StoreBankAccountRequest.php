<?php

namespace App\Domains\Finance\Requests;

use App\Domains\Finance\Enums\AccountType;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreBankAccountRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', new Enum(AccountType::class)],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'balance' => ['nullable', 'numeric', 'min:-9999999', 'max:9999999999'],
            'currency' => ['nullable', 'string', 'size:3'],
            'color' => ['nullable', 'string', 'max:20'],
        ];
    }
}
