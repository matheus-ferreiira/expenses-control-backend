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
        /** @var string[]|null */
        public ?array $tagIds = null,
        /** Destination account for type=transfer — the account being credited. */
        public ?string $destinationAccountId = null,
        /** Finance goal this transaction contributes to (aporte). */
        public ?string $goalId = null,
        /** Credit card statement being settled ("YYYY-MM"), for statement-payment transfers. */
        public ?string $statementMonth = null,
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
            tagIds: $data['tag_ids'] ?? null,
            destinationAccountId: $data['destination_account_id'] ?? null,
            goalId: $data['goal_id'] ?? null,
            statementMonth: $data['statement_month'] ?? null,
        );
    }
}
