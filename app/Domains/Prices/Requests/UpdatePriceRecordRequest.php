<?php

namespace App\Domains\Prices\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdatePriceRecordRequest extends BaseFormRequest
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
                'sometimes',
                'required',
                'uuid',
                Rule::exists('price_stores', 'id')
                    ->where('user_id', $this->user()->id)
                    ->whereNull('deleted_at'),
            ],
            'price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999999999'],
            'recorded_at' => ['sometimes', 'required', 'date'],
            'url' => ['nullable', 'url', 'max:500'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
