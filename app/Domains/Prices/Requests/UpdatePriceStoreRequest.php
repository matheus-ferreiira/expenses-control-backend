<?php

namespace App\Domains\Prices\Requests;

use App\Http\Requests\BaseFormRequest;

class UpdatePriceStoreRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
