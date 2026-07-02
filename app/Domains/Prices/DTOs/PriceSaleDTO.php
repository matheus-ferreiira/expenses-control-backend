<?php

namespace App\Domains\Prices\DTOs;

final readonly class PriceSaleDTO
{
    public function __construct(
        public string $purchaseId,
        public float $salePrice,
        public string $soldAt,
        public ?string $notes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            purchaseId: $data['purchase_id'],
            salePrice: (float) $data['sale_price'],
            soldAt: $data['sold_at'],
            notes: $data['notes'] ?? null,
        );
    }
}
