<?php

namespace App\Domains\Finance\DTOs;

use App\Domains\Finance\Enums\TransactionType;

final readonly class TransactionDTO
{
    public function __construct(
        public TransactionType $type,
        public float $amount,
        public string $description,
        public string $transactionDate,
        public ?string $accountId = null,
        public ?string $cardId = null,
        public ?string $categoryId = null,
        public ?string $notes = null,
        public bool $isRecurring = false,
        public ?array $recurrenceConfig = null,
        public ?int $totalInstallments = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            type: TransactionType::from($data['type']),
            amount: (float) $data['amount'],
            description: $data['description'],
            transactionDate: $data['transaction_date'],
            accountId: $data['account_id'] ?? null,
            cardId: $data['card_id'] ?? null,
            categoryId: $data['category_id'] ?? null,
            notes: $data['notes'] ?? null,
            isRecurring: (bool) ($data['is_recurring'] ?? false),
            recurrenceConfig: $data['recurrence_config'] ?? null,
            totalInstallments: $data['total_installments'] ?? null,
        );
    }
}
