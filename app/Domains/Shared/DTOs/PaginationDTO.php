<?php

namespace App\Domains\Shared\DTOs;

final readonly class PaginationDTO
{
    public function __construct(
        public int $page = 1,
        public int $perPage = 15,
        public ?string $sortBy = null,
        public string $sortDirection = 'asc',
        public ?string $search = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            page: (int) ($data['page'] ?? 1),
            perPage: min((int) ($data['per_page'] ?? 15), 100),
            sortBy: $data['sort_by'] ?? null,
            sortDirection: in_array($data['sort_direction'] ?? 'asc', ['asc', 'desc']) ? $data['sort_direction'] : 'asc',
            search: $data['search'] ?? null,
        );
    }
}
