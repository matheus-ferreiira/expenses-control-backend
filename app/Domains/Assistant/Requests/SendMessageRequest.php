<?php

namespace App\Domains\Assistant\Requests;

use App\Http\Requests\BaseFormRequest;

class SendMessageRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:6000'],
            'request_id' => ['required', 'uuid'],
            'date' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
