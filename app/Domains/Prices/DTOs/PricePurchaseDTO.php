<?php

namespace App\Domains\Prices\DTOs;

final readonly class PricePurchaseDTO
{
    public function __construct(
        public string $productId,
        public float $pricePaid,
        public string $purchasedAt,
        public ?string $storeId = null,
        public ?int $warrantyMonths = null,
        public ?float $currentValue = null,
        public ?string $notes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            productId: $data['product_id'],
            pricePaid: (float) $data['price_paid'],
            purchasedAt: $data['purchased_at'],
            storeId: $data['store_id'] ?? null,
            warrantyMonths: isset($data['warranty_months']) ? (int) $data['warranty_months'] : null,
            currentValue: isset($data['current_value']) ? (float) $data['current_value'] : null,
            notes: $data['notes'] ?? null,
        );
    }
}
