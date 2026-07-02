<?php

namespace App\Domains\Prices\DTOs;

final readonly class PriceStoreDTO
{
    public function __construct(
        public string $name,
        public ?string $websiteUrl = null,
        public ?string $notes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            websiteUrl: $data['website_url'] ?? null,
            notes: $data['notes'] ?? null,
        );
    }
}
