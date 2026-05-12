<?php

namespace App\Domains\Auth\Controllers;

use App\Domains\Auth\DTOs\LoginDTO;
use App\Domains\Auth\DTOs\RegisterDTO;
use App\Domains\Auth\Requests\ForgotPasswordRequest;
use App\Domains\Auth\Requests\LoginRequest;
use App\Domains\Auth\Requests\RegisterRequest;
use App\Domains\Auth\Requests\ResetPasswordRequest;
use App\Domains\Auth\Resources\AuthUserResource;
use App\Domains\Auth\Services\AuthService;
use App\Http\Controllers\Controller;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register(
            RegisterDTO::fromArray($request->validated())
        );

        return $this->created([
            'user' => new AuthUserResource($result['user']),
            'token' => $result['token'],
        ], 'Registration successful');
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login(
                LoginDTO::fromArray($request->validated())
            );

            return $this->success([
                'user' => new AuthUserResource($result['user']),
                'token' => $result['token'],
            ], 'Login successful');
        } catch (AuthenticationException) {
            return $this->error('Invalid credentials', 401);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());
        return $this->success(message: 'Logged out successfully');
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $this->authService->logoutFromAllDevices($request->user());
        return $this->success(message: 'Logged out from all devices');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success(new AuthUserResource($request->user()));
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $sent = $this->authService->sendPasswordResetLink($request->email);

        return $sent
            ? $this->success(message: 'Password reset link sent to your email')
            : $this->error('Unable to send reset link', 400);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $reset = $this->authService->resetPassword(
            $request->token,
            $request->email,
            $request->password,
        );

        return $reset
            ? $this->success(message: 'Password reset successfully')
            : $this->error('Invalid or expired reset token', 400);
    }
}
