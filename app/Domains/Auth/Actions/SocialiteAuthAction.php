<?php

namespace App\Domains\Auth\Actions;

use App\Domains\Auth\DTOs\GoogleAuthDTO;
use App\Models\User;

final class SocialiteAuthAction
{
    public function execute(GoogleAuthDTO $dto): User
    {
        $user = User::where('google_id', $dto->googleId)->first();

        if ($user) {
            return $user;
        }

        // Link google_id to existing account with same email
        $user = User::where('email', $dto->email)->first();

        if ($user) {
            $user->update([
                'google_id' => $dto->googleId,
                'avatar' => $user->avatar ?? $dto->avatar,
            ]);

            return $user;
        }

        return User::create([
            'name' => $dto->name,
            'email' => $dto->email,
            'google_id' => $dto->googleId,
            'avatar' => $dto->avatar,
            'email_verified_at' => now(),
        ]);
    }
}
