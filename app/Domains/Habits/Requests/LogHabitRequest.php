<?php

namespace App\Domains\Habits\Requests;

use App\Http\Requests\BaseFormRequest;

class LogHabitRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'completed_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
