<?php

namespace App\Domains\Tasks\Requests;

use App\Domains\Tasks\Enums\RecurrenceType;
use App\Domains\Tasks\Enums\TaskPriority;
use App\Domains\Tasks\Enums\TaskStatus;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreTaskRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', new Enum(TaskPriority::class)],
            'status' => ['nullable', new Enum(TaskStatus::class)],
            'due_date' => ['nullable', 'date'],
            'due_time' => ['nullable', 'date_format:H:i'],
            'recurrence_type' => ['nullable', new Enum(RecurrenceType::class)],
            'recurrence_config' => ['nullable', 'array'],
            'position' => ['nullable', 'integer', 'min:1'],
            'label_ids' => ['nullable', 'array'],
            'label_ids.*' => ['uuid', Rule::exists('task_labels', 'id')->where('user_id', $this->user()->id)],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['uuid', Rule::exists('task_tags', 'id')->where('user_id', $this->user()->id)],
            'task_list_id' => ['nullable', 'uuid', Rule::exists('task_lists', 'id')->where('user_id', $this->user()->id)],
            'estimated_minutes' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
