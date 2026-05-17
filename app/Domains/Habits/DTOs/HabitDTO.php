<?php

namespace App\Domains\Habits\DTOs;

use App\Domains\Habits\Enums\FrequencyType;

final readonly class HabitDTO
{
    public function __construct(
        public string $name,
        public ?string $category = null,
        public ?string $description = null,
        public FrequencyType $frequencyType = FrequencyType::Daily,
        public int $targetFrequency = 1,
        public ?string $color = null,
        public ?string $icon = null,
        public ?string $startDate = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            category: $data['category'] ?? null,
            description: $data['description'] ?? null,
            frequencyType: FrequencyType::from($data['frequency_type'] ?? FrequencyType::Daily->value),
            targetFrequency: (int) ($data['target_frequency'] ?? 1),
            color: $data['color'] ?? null,
            icon: $data['icon'] ?? null,
            startDate: $data['start_date'] ?? null,
        );
    }
}
