<?php

namespace App\Domains\Finance\Requests;

use App\Domains\Finance\Enums\TransactionType;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;
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
            'account_id' => ['nullable', 'uuid', Rule::exists('bank_accounts', 'id')->where('user_id', $this->user()->id)],
            'card_id' => ['nullable', 'uuid', Rule::exists('credit_cards', 'id')->where('user_id', $this->user()->id)],
            'category_id' => ['nullable', 'uuid', Rule::exists('transaction_categories', 'id')->where('user_id', $this->user()->id)],
            'is_recurring' => ['sometimes', 'boolean'],
            'tag_ids' => ['sometimes', 'nullable', 'array'],
            'tag_ids.*' => ['uuid', Rule::exists('transaction_tags', 'id')->where('user_id', $this->user()->id)],
        ];
    }
}
