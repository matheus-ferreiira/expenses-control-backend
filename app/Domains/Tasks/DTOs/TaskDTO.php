<?php

namespace App\Domains\Tasks\DTOs;

use App\Domains\Tasks\Enums\RecurrenceType;
use App\Domains\Tasks\Enums\TaskPriority;
use App\Domains\Tasks\Enums\TaskStatus;

final readonly class TaskDTO
{
    public function __construct(
        public string $title,
        public ?string $description = null,
        public TaskPriority $priority = TaskPriority::Normal,
        public TaskStatus $status = TaskStatus::Pending,
        public ?string $dueDate = null,
        public RecurrenceType $recurrenceType = RecurrenceType::None,
        public ?array $recurrenceConfig = null,
        public ?int $position = null,
        public array $labelIds = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'],
            description: $data['description'] ?? null,
            priority: TaskPriority::from($data['priority'] ?? TaskPriority::Normal->value),
            status: TaskStatus::from($data['status'] ?? TaskStatus::Pending->value),
            dueDate: $data['due_date'] ?? null,
            recurrenceType: RecurrenceType::from($data['recurrence_type'] ?? RecurrenceType::None->value),
            recurrenceConfig: $data['recurrence_config'] ?? null,
            position: $data['position'] ?? null,
            labelIds: $data['label_ids'] ?? [],
        );
    }
}
