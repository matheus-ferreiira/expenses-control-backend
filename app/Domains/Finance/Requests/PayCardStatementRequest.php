<?php

namespace App\Domains\Finance\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class PayCardStatementRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'account_id' => ['required', 'uuid', Rule::exists('bank_accounts', 'id')->where('user_id', $this->user()->id)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'statement_month' => ['required', 'date_format:Y-m'],
            'payment_date' => ['nullable', 'date'],
        ];
    }
}
