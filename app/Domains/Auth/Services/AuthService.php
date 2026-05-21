<?php

namespace App\Domains\Auth\Services;

use App\Domains\Auth\Actions\LoginAction;
use App\Domains\Auth\Actions\RegisterAction;
use App\Domains\Auth\Actions\SocialiteAuthAction;
use App\Domains\Auth\DTOs\GoogleAuthDTO;
use App\Domains\Auth\DTOs\LoginDTO;
use App\Domains\Auth\DTOs\RegisterDTO;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Password;

final class AuthService
{
    public function __construct(
        private readonly LoginAction $loginAction,
        private readonly RegisterAction $registerAction,
        private readonly SocialiteAuthAction $socialiteAuthAction,
    ) {}

    public function register(RegisterDTO $dto): array
    {
        $user = $this->registerAction->execute($dto);
        $token = $user->createToken('api-token')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    /**
     * @throws AuthenticationException
     */
    public function login(LoginDTO $dto): array
    {
        return $this->loginAction->execute($dto);
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    public function logoutFromAllDevices(User $user): void
    {
        $user->tokens()->delete();
    }

    public function sendPasswordResetLink(string $email): bool
    {
        $status = Password::sendResetLink(['email' => $email]);

        return $status === Password::RESET_LINK_SENT;
    }

    public function authenticateWithGoogle(GoogleAuthDTO $dto): array
    {
        $user = $this->socialiteAuthAction->execute($dto);
        $token = $user->createToken('google-token')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    public function resetPassword(string $token, string $email, string $password): bool
    {
        $status = Password::reset(
            ['token' => $token, 'email' => $email, 'password' => $password, 'password_confirmation' => $password],
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])->save();
                $user->tokens()->delete();
            }
        );

        return $status === Password::PASSWORD_RESET;
    }
}
