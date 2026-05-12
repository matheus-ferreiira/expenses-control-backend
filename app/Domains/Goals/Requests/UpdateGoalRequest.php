<?php

namespace App\Domains\Goals\Requests;

use App\Domains\Goals\Enums\GoalStatus;
use App\Domains\Goals\Enums\GoalType;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateGoalRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['nullable', new Enum(GoalType::class)],
            'status' => ['nullable', new Enum(GoalStatus::class)],
            'description' => ['nullable', 'string'],
            'target_amount' => ['nullable', 'numeric', 'min:0'],
            'target_date' => ['nullable', 'date'],
        ];
    }
}
