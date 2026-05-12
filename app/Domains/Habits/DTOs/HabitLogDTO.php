<?php

namespace App\Domains\Habits\DTOs;

final readonly class HabitLogDTO
{
    public function __construct(
        public string $completedDate,
        public ?string $notes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            completedDate: $data['completed_date'],
            notes: $data['notes'] ?? null,
        );
    }
}
