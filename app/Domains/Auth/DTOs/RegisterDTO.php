<?php

namespace App\Domains\Auth\DTOs;

final readonly class RegisterDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public string $timezone = 'America/Sao_Paulo',
        public string $locale = 'pt_BR',
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            email: $data['email'],
            password: $data['password'],
            timezone: $data['timezone'] ?? 'America/Sao_Paulo',
            locale: $data['locale'] ?? 'pt_BR',
        );
    }
}
