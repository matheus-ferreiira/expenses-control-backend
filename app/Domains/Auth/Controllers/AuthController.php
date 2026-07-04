<?php

namespace App\Domains\Auth\Controllers;

use App\Domains\Auth\Actions\ResetUserDataAction;
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
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly ResetUserDataAction $resetUserDataAction,
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

    /** Update profile fields (name). Email is the login identity — not editable here. */
    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        $user = $request->user();
        $user->update(['name' => $validated['name']]);

        return $this->success(new AuthUserResource($user->fresh()), 'Profile updated');
    }

    /** Change password — requires the current one; keeps the current session valid. */
    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $request->user()->update(['password' => bcrypt($validated['password'])]);

        return $this->success(message: 'Password updated');
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.modules' => ['sometimes', 'array'],
            'settings.modules.*' => ['boolean'],
        ]);

        $user = $request->user();
        $current = $user->settings ?? [];
        $user->update(['settings' => array_merge($current, $validated['settings'])]);

        return $this->success(new AuthUserResource($user->fresh()), 'Settings updated');
    }

    public function resetData(Request $request): JsonResponse
    {
        $this->resetUserDataAction->execute($request->user());

        return $this->success(message: 'All user data has been reset');
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->sendPasswordResetLink($request->validated('email'));

        return $this->success(message: 'If that email is registered, a reset link has been sent');
    }

    public function googleRedirect(): SymfonyRedirectResponse
    {
        $redirect = Socialite::driver('google')->stateless()->redirect();
        Log::info('Google OAuth redirect', [
            'redirect_uri' => config('services.google.redirect'),
            'client_id' => substr((string) config('services.google.client_id'), 0, 20).'...',
            'target_url' => $redirect->getTargetUrl(),
        ]);

        return $redirect;
    }

    public function googleCallback(): RedirectResponse
    {
        $frontendUrl = rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/');

        try {
            // Log any error Google sent back (redirect_uri_mismatch, access_denied, etc.)
            if (request()->has('error')) {
                Log::error('Google OAuth: Google returned an error', [
                    'error' => request()->query('error'),
                    'error_description' => request()->query('error_description'),
                ]);
            }

            $googleUser = Socialite::driver('google')->stateless()->user();
            $result = $this->authService->authenticateWithGoogle(
                GoogleAuthDTO::fromSocialite($googleUser)
            );

            return redirect("{$frontendUrl}/auth/google/callback?token={$result['token']}");
        } catch (\Exception $e) {
            Log::error('Google OAuth callback failed', [
                'error' => $e->getMessage(),
                'google_error' => request()->query('error'),
                'google_error_description' => request()->query('error_description'),
            ]);

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
