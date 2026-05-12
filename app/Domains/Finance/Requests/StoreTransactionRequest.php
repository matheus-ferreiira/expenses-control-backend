<?php

namespace App\Domains\Finance\Requests;

use App\Domains\Finance\Enums\TransactionType;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreTransactionRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', new Enum(TransactionType::class)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
            'transaction_date' => ['required', 'date'],
            'account_id' => ['nullable', 'uuid', 'exists:bank_accounts,id'],
            'card_id' => ['nullable', 'uuid', 'exists:credit_cards,id'],
            'category_id' => ['nullable', 'uuid', 'exists:transaction_categories,id'],
            'is_recurring' => ['nullable', 'boolean'],
            'recurrence_config' => ['nullable', 'array'],
            'total_installments' => ['nullable', 'integer', 'min:2', 'max:60'],
        ];
    }
}
