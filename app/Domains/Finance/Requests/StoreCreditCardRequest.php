<?php

namespace App\Domains\Finance\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StoreCreditCardRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'bank_account_id' => ['nullable', 'uuid', Rule::exists('bank_accounts', 'id')->where('user_id', $this->user()->id)],
            'name' => ['required', 'string', 'max:255'],
            'limit_amount' => ['required', 'numeric', 'min:0'],
            'closing_day' => ['required', 'integer', 'min:1', 'max:31'],
            'due_day' => ['required', 'integer', 'min:1', 'max:31'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
        ];
    }
}
