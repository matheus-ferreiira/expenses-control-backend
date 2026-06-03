<?php

namespace App\Domains\Finance\Requests;

use App\Http\Requests\BaseFormRequest;

class UpdateFinanceGoalRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'target_amount' => ['sometimes', 'numeric', 'min:0.01', 'max:9999999999'],
            'monthly_contribution' => ['sometimes', 'numeric', 'min:0', 'max:9999999999'],
            'deadline' => ['sometimes', 'nullable', 'date'],
            'color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:50'],
            'bank_account_id' => ['sometimes', 'nullable', 'uuid', 'exists:bank_accounts,id'],
            'status' => ['sometimes', 'string', 'in:active,completed,paused'],
        ];
    }
}
