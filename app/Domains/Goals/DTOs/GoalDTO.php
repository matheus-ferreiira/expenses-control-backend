<?php

namespace App\Domains\Goals\DTOs;

use App\Domains\Goals\Enums\GoalStatus;
use App\Domains\Goals\Enums\GoalType;

final readonly class GoalDTO
{
    public function __construct(
        public string $title,
        public GoalType $type,
        public ?string $description = null,
        public ?float $targetAmount = null,
        public float $currentAmount = 0.0,
        public ?string $targetDate = null,
        public GoalStatus $status = GoalStatus::Active,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'],
            type: GoalType::from($data['type']),
            description: $data['description'] ?? null,
            targetAmount: isset($data['target_amount']) ? (float) $data['target_amount'] : null,
            currentAmount: (float) ($data['current_amount'] ?? 0),
            targetDate: $data['target_date'] ?? null,
            status: GoalStatus::from($data['status'] ?? GoalStatus::Active->value),
        );
    }
}
