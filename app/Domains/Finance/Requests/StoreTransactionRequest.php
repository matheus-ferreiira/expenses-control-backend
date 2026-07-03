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
            // Card purchases live on the card only (no account); everything else needs an account.
            'account_id' => [
                Rule::requiredIf(fn () => $this->input('type') === 'transfer' || ! $this->filled('card_id')),
                'nullable', 'uuid', Rule::exists('bank_accounts', 'id')->where('user_id', $this->user()->id),
            ],
            // Transfers go to another account OR to a credit card (statement payment).
            'destination_account_id' => [
                Rule::when($this->input('type') === 'transfer' && ! $this->filled('card_id'), ['required', 'uuid', 'different:account_id', Rule::exists('bank_accounts', 'id')->where('user_id', $this->user()->id)]),
                Rule::when($this->input('type') !== 'transfer' || $this->filled('card_id'), ['nullable']),
            ],
            'card_id' => ['nullable', 'uuid', Rule::exists('credit_cards', 'id')->where('user_id', $this->user()->id)],
            'statement_month' => ['nullable', 'date_format:Y-m'],
            'category_id' => ['nullable', 'uuid', Rule::exists('transaction_categories', 'id')->where('user_id', $this->user()->id)],
            'goal_id' => ['nullable', 'uuid', Rule::exists('finance_goals', 'id')->where('user_id', $this->user()->id)],
            'is_recurring' => ['nullable', 'boolean'],
            'recurrence_config' => ['nullable', 'array'],
            'total_installments' => ['nullable', 'integer', 'min:2', 'max:60'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['uuid', Rule::exists('transaction_tags', 'id')->where('user_id', $this->user()->id)],
        ];
    }
}
