<?php

namespace App\Domains\Calendar\Requests;

use App\Http\Requests\BaseFormRequest;

class CalendarEventFilterRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ];
    }
}
