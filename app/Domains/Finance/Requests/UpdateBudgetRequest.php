<?php

namespace App\Domains\Finance\Requests;

use App\Http\Requests\BaseFormRequest;

class UpdateBudgetRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'base_amount' => ['sometimes', 'numeric', 'min:0.01', 'max:9999999999'],
            'is_template' => ['sometimes', 'boolean'],
            'items' => ['sometimes', 'array'],
            'items.*.category_id' => ['required_with:items', 'uuid', 'exists:transaction_categories,id'],
            'items.*.amount' => ['required_with:items', 'numeric', 'min:0.01', 'max:9999999999'],
            'items.*.percentage' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
