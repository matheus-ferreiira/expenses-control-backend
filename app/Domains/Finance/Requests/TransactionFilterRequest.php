<?php

namespace App\Domains\Finance\Requests;

use App\Domains\Finance\Enums\TransactionType;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rules\Enum;

class TransactionFilterRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['nullable', new Enum(TransactionType::class)],
            'account_id' => ['nullable', 'uuid'],
            'card_id' => ['nullable', 'uuid'],
            'category_id' => ['nullable', 'uuid'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'year' => ['nullable', 'integer', 'min:2000'],
            'search' => ['nullable', 'string', 'max:255'],
            'sort_by' => ['nullable', 'in:transaction_date,amount,description,created_at'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ];
    }
}
