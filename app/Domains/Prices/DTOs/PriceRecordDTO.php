<?php

namespace App\Domains\Prices\DTOs;

final readonly class PriceRecordDTO
{
    public function __construct(
        public string $productId,
        public string $storeId,
        public float $price,
        public string $recordedAt,
        public ?string $url = null,
        public ?string $notes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            productId: $data['product_id'],
            storeId: $data['store_id'],
            price: (float) $data['price'],
            recordedAt: $data['recorded_at'],
            url: $data['url'] ?? null,
            notes: $data['notes'] ?? null,
        );
    }
}
