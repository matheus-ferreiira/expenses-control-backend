<?php

namespace App\Domains\Prices\DTOs;

use App\Domains\Prices\Enums\ProductStatus;

final readonly class PriceProductDTO
{
    public function __construct(
        public string $name,
        public ?string $categoryId = null,
        public ?string $brand = null,
        public ?string $model = null,
        public ?string $specs = null,
        public ?string $notes = null,
        public ?float $targetPrice = null,
        public ?float $launchPrice = null,
        public ProductStatus $status = ProductStatus::Tracking,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            categoryId: $data['category_id'] ?? null,
            brand: $data['brand'] ?? null,
            model: $data['model'] ?? null,
            specs: $data['specs'] ?? null,
            notes: $data['notes'] ?? null,
            targetPrice: isset($data['target_price']) ? (float) $data['target_price'] : null,
            launchPrice: isset($data['launch_price']) ? (float) $data['launch_price'] : null,
            status: ProductStatus::from($data['status'] ?? ProductStatus::Tracking->value),
        );
    }
}
