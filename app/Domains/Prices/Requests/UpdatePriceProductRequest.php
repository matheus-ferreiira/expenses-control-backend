<?php

namespace App\Domains\Prices\Requests;

use App\Domains\Prices\Enums\ProductStatus;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdatePriceProductRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'category_id' => [
                'nullable',
                'uuid',
                Rule::exists('price_categories', 'id')
                    ->where('user_id', $this->user()->id)
                    ->whereNull('deleted_at'),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:150'],
            'specs' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'target_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'launch_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'status' => ['sometimes', new Enum(ProductStatus::class)],
        ];
    }
}
