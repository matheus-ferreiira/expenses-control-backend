<?php

namespace App\Domains\Tasks\DTOs;

final readonly class TaskTagDTO
{
    public function __construct(
        public ?string $name = null,
        public ?string $color = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            color: array_key_exists('color', $data) ? $data['color'] : null,
        );
    }
}
