<?php

namespace App\Domains\Calendar\Requests;

use App\Http\Requests\BaseFormRequest;

class StoreCalendarEventRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_all_day' => ['nullable', 'boolean'],
            'color' => ['nullable', 'string', 'max:20'],
        ];
    }
}
