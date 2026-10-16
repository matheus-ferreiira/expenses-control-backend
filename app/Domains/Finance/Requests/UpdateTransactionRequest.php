<?php

namespace App\Domains\Finance\Requests;

use App\Domains\Finance\Enums\RecurrenceUpdateScope;
use App\Domains\Finance\Enums\TransactionType;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Exists;

class UpdateTransactionRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', new Enum(TransactionType::class)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'transaction_date' => ['required', 'date'],
            'account_id' => [
                'sometimes', 'required',
                // A card purchase never debits a bank account too (the statement payment does).
                Rule::prohibitedIf(fn () => $this->input('type') !== 'transfer' && $this->filled('card_id')),
                'uuid', Rule::exists('bank_accounts', 'id')->where('user_id', $this->user()->id),
            ],
            'destination_account_id' => ['nullable', 'uuid', Rule::exists('bank_accounts', 'id')->where('user_id', $this->user()->id)],
            'card_id' => ['nullable', 'uuid', Rule::exists('credit_cards', 'id')->where('user_id', $this->user()->id)],
            'category_id' => ['nullable', 'uuid', $this->categoryRule()],
            'goal_id' => ['nullable', 'uuid', Rule::exists('finance_goals', 'id')->where('user_id', $this->user()->id)],
            'is_recurring' => ['sometimes', 'boolean'],
            'is_reimbursement' => ['sometimes', 'boolean'],
            'recurrence_config' => ['sometimes', 'nullable', 'array'],
            'scope' => ['nullable', new Enum(RecurrenceUpdateScope::class)],
            'tag_ids' => ['sometimes', 'nullable', 'array'],
            'tag_ids.*' => ['uuid', Rule::exists('transaction_tags', 'id')->where('user_id', $this->user()->id)],
        ];
    }

    public function messages(): array
    {
        return [
            'account_id.prohibited' => 'Escolha a conta ou o cartão, não os dois: a compra no cartão sai da conta só no pagamento da fatura.',
            'category_id.exists' => 'Escolha uma categoria do mesmo tipo do lançamento (de despesa para despesa, de receita para receita).',
        ];
    }

    /** A category belongs to the user and, for income/expense, has the same type as the transaction (D-27). */
    private function categoryRule(): Exists
    {
        $rule = Rule::exists('transaction_categories', 'id')->where('user_id', $this->user()->id);
        $type = $this->input('type');

        return in_array($type, ['income', 'expense'], true) ? $rule->where('type', $type) : $rule;
    }
}
