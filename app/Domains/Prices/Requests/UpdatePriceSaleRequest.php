<?php

namespace App\Domains\Prices\Requests;

use App\Http\Requests\BaseFormRequest;

class UpdatePriceSaleRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'sale_price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999999999'],
            'sold_at' => ['sometimes', 'required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
