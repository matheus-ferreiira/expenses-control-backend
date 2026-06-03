<?php

namespace App\Domains\Finance\Requests;

use App\Http\Requests\BaseFormRequest;

class StoreBudgetRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'base_amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999'],
            'is_template' => ['sometimes', 'boolean'],
            'items' => ['sometimes', 'array'],
            'items.*.category_id' => ['required_with:items', 'uuid', 'exists:transaction_categories,id'],
            'items.*.amount' => ['required_with:items', 'numeric', 'min:0.01', 'max:9999999999'],
            'items.*.percentage' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
