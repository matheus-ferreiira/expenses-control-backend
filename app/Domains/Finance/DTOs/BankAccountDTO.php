<?php

namespace App\Domains\Finance\DTOs;

use App\Domains\Finance\Enums\AccountType;

final readonly class BankAccountDTO
{
    public function __construct(
        public string $name,
        public AccountType $type = AccountType::Checking,
        public ?string $bankName = null,
        public float $balance = 0.0,
        public string $currency = 'BRL',
        public ?string $color = null,
        public bool $isActive = true,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            type: AccountType::from($data['type'] ?? AccountType::Checking->value),
            bankName: $data['bank_name'] ?? null,
            balance: (float) ($data['balance'] ?? 0),
            currency: $data['currency'] ?? 'BRL',
            color: $data['color'] ?? null,
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }
}
