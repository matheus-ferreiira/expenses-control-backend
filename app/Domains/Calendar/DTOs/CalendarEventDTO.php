<?php

namespace App\Domains\Calendar\DTOs;

use App\Domains\Calendar\Enums\EventSource;

final readonly class CalendarEventDTO
{
    public function __construct(
        public string $title,
        public string $startDate,
        public string $endDate,
        public ?string $description = null,
        public bool $isAllDay = false,
        public ?string $color = null,
        public EventSource $source = EventSource::Manual,
        public ?string $externalId = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'],
            startDate: $data['start_date'],
            endDate: $data['end_date'],
            description: $data['description'] ?? null,
            isAllDay: (bool) ($data['is_all_day'] ?? false),
            color: $data['color'] ?? null,
            source: EventSource::from($data['source'] ?? EventSource::Manual->value),
            externalId: $data['external_id'] ?? null,
        );
    }
}
