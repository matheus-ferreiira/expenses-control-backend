<?php

namespace App\Domains\Notes\DTOs;

final readonly class NoteDTO
{
    public function __construct(
        public string $title = '',
        public ?string $content = null,
        public bool $isPinned = false,
        public bool $isFavorite = false,
        public array $tagIds = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? '',
            content: $data['content'] ?? null,
            isPinned: (bool) ($data['is_pinned'] ?? false),
            isFavorite: (bool) ($data['is_favorite'] ?? false),
            tagIds: $data['tag_ids'] ?? [],
        );
    }
}
