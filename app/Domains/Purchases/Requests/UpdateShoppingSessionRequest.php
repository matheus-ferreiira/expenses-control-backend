<?php

namespace App\Domains\Purchases\Requests;

use App\Http\Requests\BaseFormRequest;

class UpdateShoppingSessionRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
        ];
    }
}
