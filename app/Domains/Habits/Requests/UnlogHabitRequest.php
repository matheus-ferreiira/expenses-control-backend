<?php

namespace App\Domains\Habits\Requests;

use App\Http\Requests\BaseFormRequest;

class UnlogHabitRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
        ];
    }
}
