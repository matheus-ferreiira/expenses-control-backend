<?php

namespace App\Domains\Prices\DTOs;

final readonly class PriceCategoryDTO
{
    public function __construct(
        public string $name,
        public ?string $icon = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            icon: $data['icon'] ?? null,
        );
    }
}
