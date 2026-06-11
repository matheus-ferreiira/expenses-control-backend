<?php

namespace App\Domains\Tasks\DTOs;

use App\Domains\Tasks\Enums\RecurrenceType;
use App\Domains\Tasks\Enums\TaskPriority;
use App\Domains\Tasks\Enums\TaskStatus;

final readonly class TaskDTO
{
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?TaskPriority $priority = null,
        public ?TaskStatus $status = null,
        public ?string $dueDate = null,
        public ?string $dueTime = null,
        public ?RecurrenceType $recurrenceType = null,
        public ?array $recurrenceConfig = null,
        public ?int $position = null,
        public ?array $labelIds = null,
        public ?array $tagIds = null,
        public ?string $taskListId = null,
        public ?int $estimatedMinutes = null,
        public array $provided = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? null,
            description: $data['description'] ?? null,
            priority: isset($data['priority']) ? TaskPriority::from($data['priority']) : null,
            status: isset($data['status']) ? TaskStatus::from($data['status']) : null,
            dueDate: $data['due_date'] ?? null,
            dueTime: $data['due_time'] ?? null,
            recurrenceType: isset($data['recurrence_type']) ? RecurrenceType::from($data['recurrence_type']) : null,
            recurrenceConfig: $data['recurrence_config'] ?? null,
            position: $data['position'] ?? null,
            labelIds: array_key_exists('label_ids', $data) ? ($data['label_ids'] ?? []) : null,
            tagIds: array_key_exists('tag_ids', $data) ? ($data['tag_ids'] ?? []) : null,
            taskListId: $data['task_list_id'] ?? null,
            estimatedMinutes: $data['estimated_minutes'] ?? null,
            provided: array_keys($data),
        );
    }
}
