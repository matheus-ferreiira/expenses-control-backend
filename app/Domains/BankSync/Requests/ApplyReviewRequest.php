<?php

namespace App\Domains\BankSync\Requests;

use App\Domains\BankSync\Services\BankReconciler;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class ApplyReviewRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.id' => ['required', 'uuid', 'distinct'],
            'items.*.action' => ['required', Rule::in(BankReconciler::ACTIONS)],
            'items.*.category_id' => ['nullable', 'uuid'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.account_id' => ['nullable', 'uuid'],
            'items.*.card_id' => ['nullable', 'uuid'],
            'items.*.transaction_id' => ['nullable', 'uuid'],
            'items.*.is_reimbursement' => ['sometimes', 'boolean'],
            // Expense shared with others: only this part is the user's spending (the rest comes back as reimbursements)
            'items.*.own_share_cents' => ['nullable', 'integer', 'min:1'],
            // Remember this choice for the same counterpart next time (default true)
            'items.*.remember' => ['sometimes', 'boolean'],
        ];
    }
}
