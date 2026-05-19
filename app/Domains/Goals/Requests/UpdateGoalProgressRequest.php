<?php

namespace App\Domains\Goals\Requests;

use App\Http\Requests\BaseFormRequest;

class UpdateGoalProgressRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'current_amount' => ['required', 'numeric', 'min:0'],
        ];
    }
}
