<?php

namespace App\Domains\Prices\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdatePricePurchaseRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'product_id' => [
                'sometimes',
                'required',
                'uuid',
                Rule::exists('price_products', 'id')
                    ->where('user_id', $this->user()->id)
                    ->whereNull('deleted_at'),
            ],
            'store_id' => [
                'nullable',
                'uuid',
                Rule::exists('price_stores', 'id')
                    ->where('user_id', $this->user()->id)
                    ->whereNull('deleted_at'),
            ],
            'price_paid' => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999999999'],
            'purchased_at' => ['sometimes', 'required', 'date'],
            'warranty_months' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'current_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
