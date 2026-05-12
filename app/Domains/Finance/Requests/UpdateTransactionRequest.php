<?php

namespace App\Domains\Finance\Requests;

use App\Domains\Finance\Enums\TransactionType;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateTransactionRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'required', new Enum(TransactionType::class)],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'description' => ['sometimes', 'required', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
            'transaction_date' => ['sometimes', 'required', 'date'],
            'account_id' => ['nullable', 'uuid', 'exists:bank_accounts,id'],
            'card_id' => ['nullable', 'uuid', 'exists:credit_cards,id'],
            'category_id' => ['nullable', 'uuid', 'exists:transaction_categories,id'],
        ];
    }
}
