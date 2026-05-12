<?php

namespace App\Domains\Tasks\Requests;

use App\Domains\Tasks\Enums\TaskPriority;
use App\Domains\Tasks\Enums\TaskStatus;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rules\Enum;

class TaskFilterRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['nullable', new Enum(TaskStatus::class)],
            'priority' => ['nullable', new Enum(TaskPriority::class)],
            'label_id' => ['nullable', 'uuid'],
            'search' => ['nullable', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
            'archived' => ['nullable', 'boolean'],
            'sort_by' => ['nullable', 'in:position,due_date,created_at,priority,title'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
