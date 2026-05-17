<?php

namespace App\Domains\Habits\Requests;

use App\Domains\Habits\Enums\FrequencyType;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreHabitRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'frequency_type' => ['nullable', new Enum(FrequencyType::class)],
            'target_frequency' => ['nullable', 'integer', 'min:1', 'max:365'],
            'color' => ['nullable', 'string', 'max:20'],
            'icon' => ['nullable', 'string', 'max:50'],
            'start_date' => ['nullable', 'date'],
        ];
    }
}
