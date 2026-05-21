<?php

namespace App\Domains\Auth\Controllers;

use App\Domains\Auth\DTOs\GoogleAuthDTO;
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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

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
        $this->authService->sendPasswordResetLink($request->validated('email'));

        return $this->success(message: 'If that email is registered, a reset link has been sent');
    }

    public function googleRedirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function googleCallback(): RedirectResponse
    {
        $frontendUrl = rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/');

        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
            $result = $this->authService->authenticateWithGoogle(
                GoogleAuthDTO::fromSocialite($googleUser)
            );

            return redirect("{$frontendUrl}/auth/google/callback?token={$result['token']}");
        } catch (\Exception) {
            return redirect("{$frontendUrl}/login?error=google_auth_failed");
        }
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $reset = $this->authService->resetPassword(
            $validated['token'],
            $validated['email'],
            $validated['password'],
        );

        return $reset
            ? $this->success(message: 'Password reset successfully')
            : $this->error('Invalid or expired reset token', 400);
    }
}
