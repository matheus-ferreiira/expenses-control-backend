<?php

namespace App\Domains\Tasks\DTOs;

final readonly class TaskListDTO
{
    public function __construct(
        public ?string $name = null,
        public ?string $color = null,
        public ?string $icon = null,
        public ?int $position = null,
        public ?bool $isDefault = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            color: array_key_exists('color', $data) ? $data['color'] : null,
            icon: array_key_exists('icon', $data) ? $data['icon'] : null,
            position: $data['position'] ?? null,
            isDefault: $data['is_default'] ?? null,
        );
    }
}
