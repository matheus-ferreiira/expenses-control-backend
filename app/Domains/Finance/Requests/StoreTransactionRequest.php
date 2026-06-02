<?php

namespace App\Domains\Finance\Requests;

use App\Domains\Finance\Enums\TransactionType;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreTransactionRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', new Enum(TransactionType::class)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'transaction_date' => ['required', 'date'],
            'account_id' => ['required', 'uuid', Rule::exists('bank_accounts', 'id')->where('user_id', $this->user()->id)],
            'destination_account_id' => [
                Rule::when($this->input('type') === 'transfer', ['required', 'uuid', 'different:account_id', Rule::exists('bank_accounts', 'id')->where('user_id', $this->user()->id)]),
                Rule::when($this->input('type') !== 'transfer', ['nullable']),
            ],
            'card_id' => ['nullable', 'uuid', Rule::exists('credit_cards', 'id')->where('user_id', $this->user()->id)],
            'category_id' => ['nullable', 'uuid', Rule::exists('transaction_categories', 'id')->where('user_id', $this->user()->id)],
            'is_recurring' => ['nullable', 'boolean'],
            'recurrence_config' => ['nullable', 'array'],
            'total_installments' => ['nullable', 'integer', 'min:2', 'max:60'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['uuid', Rule::exists('transaction_tags', 'id')->where('user_id', $this->user()->id)],
        ];
    }
}
