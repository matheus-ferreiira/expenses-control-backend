<?php

namespace App\Domains\Tasks\Requests;

use App\Domains\Tasks\Enums\RecurrenceType;
use App\Domains\Tasks\Enums\TaskPriority;
use App\Domains\Tasks\Enums\TaskStatus;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateTaskRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', new Enum(TaskPriority::class)],
            'status' => ['nullable', new Enum(TaskStatus::class)],
            'due_date' => ['nullable', 'date'],
            'recurrence_type' => ['nullable', new Enum(RecurrenceType::class)],
            'recurrence_config' => ['nullable', 'array'],
            'label_ids' => ['nullable', 'array'],
            'label_ids.*' => ['uuid', Rule::exists('task_labels', 'id')->where('user_id', $this->user()->id)],
        ];
    }
}
