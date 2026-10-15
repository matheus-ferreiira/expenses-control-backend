<?php

namespace App\Domains\Finance\Requests;

use App\Domains\Finance\Enums\RecurrenceUpdateScope;
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
            'notes' => ['nullable', 'string', 'max:2000'],
            'transaction_date' => ['sometimes', 'required', 'date'],
            'account_id' => [
                'sometimes', 'required',
                // A card purchase never debits a bank account too (the statement payment does).
                Rule::prohibitedIf(fn () => $this->input('type') !== 'transfer' && $this->filled('card_id')),
                'uuid', Rule::exists('bank_accounts', 'id')->where('user_id', $this->user()->id),
            ],
            'destination_account_id' => ['nullable', 'uuid', Rule::exists('bank_accounts', 'id')->where('user_id', $this->user()->id)],
            'card_id' => ['nullable', 'uuid', Rule::exists('credit_cards', 'id')->where('user_id', $this->user()->id)],
            'category_id' => ['nullable', 'uuid', Rule::exists('transaction_categories', 'id')->where('user_id', $this->user()->id)],
            'goal_id' => ['nullable', 'uuid', Rule::exists('finance_goals', 'id')->where('user_id', $this->user()->id)],
            'is_recurring' => ['sometimes', 'boolean'],
            'recurrence_config' => ['sometimes', 'nullable', 'array'],
            'scope' => ['nullable', new Enum(RecurrenceUpdateScope::class)],
            'tag_ids' => ['sometimes', 'nullable', 'array'],
            'tag_ids.*' => ['uuid', Rule::exists('transaction_tags', 'id')->where('user_id', $this->user()->id)],
        ];
    }

    public function messages(): array
    {
        return ['account_id.prohibited' => 'Escolha a conta ou o cartão, não os dois: a compra no cartão sai da conta só no pagamento da fatura.'];
    }
}
