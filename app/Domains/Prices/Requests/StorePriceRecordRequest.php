<?php

namespace App\Domains\Prices\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StorePriceRecordRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'product_id' => [
                'required',
                'uuid',
                Rule::exists('price_products', 'id')
                    ->where('user_id', $this->user()->id)
                    ->whereNull('deleted_at'),
            ],
            'store_id' => [
                'required',
                'uuid',
                Rule::exists('price_stores', 'id')
                    ->where('user_id', $this->user()->id)
                    ->whereNull('deleted_at'),
            ],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'recorded_at' => ['required', 'date'],
            'url' => ['nullable', 'url', 'max:500'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
