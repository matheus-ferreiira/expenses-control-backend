<?php

namespace App\Domains\Prices\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StorePriceSaleRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'purchase_id' => [
                'required',
                'uuid',
                Rule::exists('price_purchases', 'id')
                    ->where('user_id', $this->user()->id)
                    ->whereNull('deleted_at'),
                // One sale per purchase
                Rule::unique('price_sales', 'purchase_id')->whereNull('deleted_at'),
            ],
            'sale_price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'sold_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
