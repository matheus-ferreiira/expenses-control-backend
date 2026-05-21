<?php

namespace App\Domains\Auth\DTOs;

use Laravel\Socialite\Contracts\User as SocialiteUser;

final readonly class GoogleAuthDTO
{
    public function __construct(
        public string $googleId,
        public string $name,
        public string $email,
        public ?string $avatar = null,
    ) {}

    public static function fromSocialite(SocialiteUser $user): self
    {
        return new self(
            googleId: $user->getId(),
            name: $user->getName() ?? $user->getEmail(),
            email: $user->getEmail(),
            avatar: $user->getAvatar(),
        );
    }
}
