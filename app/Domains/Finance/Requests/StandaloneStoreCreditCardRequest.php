<?php

namespace App\Domains\Finance\Requests;

use Illuminate\Validation\Rule;

class StandaloneStoreCreditCardRequest extends StoreCreditCardRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'bank_account_id' => [
                'required',
                'string',
                Rule::exists('bank_accounts', 'id')->where('user_id', $this->user()->id),
            ],
        ]);
    }

    public function messages(): array
    {
        return [
            'bank_account_id.required' => 'Selecione a conta bancária que pagará este cartão',
            'bank_account_id.exists'   => 'Selecione a conta bancária que pagará este cartão',
        ];
    }
}
