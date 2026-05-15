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
        public ?RecurrenceType $recurrenceType = null,
        public ?array $recurrenceConfig = null,
        public ?int $position = null,
        public ?array $labelIds = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? null,
            description: array_key_exists('description', $data) ? $data['description'] : null,
            priority: isset($data['priority']) ? TaskPriority::from($data['priority']) : null,
            status: isset($data['status']) ? TaskStatus::from($data['status']) : null,
            dueDate: array_key_exists('due_date', $data) ? $data['due_date'] : null,
            recurrenceType: isset($data['recurrence_type']) ? RecurrenceType::from($data['recurrence_type']) : null,
            recurrenceConfig: array_key_exists('recurrence_config', $data) ? $data['recurrence_config'] : null,
            position: $data['position'] ?? null,
            labelIds: array_key_exists('label_ids', $data) ? ($data['label_ids'] ?? []) : null,
        );
    }
}
