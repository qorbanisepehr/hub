<?php

namespace App\Domains\Auth\Controllers;

use App\Contracts\Authorization;
use App\Domains\Auth\Events\LoginFailed;
use App\Domains\Auth\Events\LoginSucceeded;
use App\Domains\Auth\Events\LogoutSucceeded;
use App\Domains\Auth\Requests\LoginRequest;
use App\Domains\Auth\Requests\LoginWithPasswordRequest;
use App\Domains\Auth\Requests\VerifyOtpRequest;
use App\Domains\Auth\Resources\UserResource;
use App\Domains\Auth\Services\AuthService;
use App\Enums\OtpContext;
use App\Http\Responses\OtpResponder;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController
{
    use OtpResponder;

    public function __construct(
        private OtpService $otpService,
        private AuthService $authService,
        private Authorization $authorizationService,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->authService->resolveUser($request->identifier);

        if (! $user) {
            event(new LoginFailed($request->identifier, 'user_not_found'));

            return response()->json([
                'message' => __('auth.failed'),
            ], 401);
        }

        if ($inactive = $this->checkInactive($user)) {
            return $inactive;
        }

        $channel = $this->authService->channelFor($request->identifier);

        $status = $this->otpService->sendWithCooldown($user, $channel, OtpContext::Login);

        $response = $this->respondToSend($user, $channel, OtpContext::Login, $status);

        $data = $response->getData(true);
        $data['destination'] = $this->authService->destination($request->identifier, $user);

        return response()->json($data, $response->getStatusCode());
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $user = $this->authService->resolveUser($request->identifier);

        if (! $user) {
            return response()->json([
                'message' => __('auth.invalid_otp'),
            ], 422);
        }

        if ($inactive = $this->checkInactive($user)) {
            return $inactive;
        }

        $channel = $this->authService->channelFor($request->identifier);
        $status = $this->otpService->attemptVerification($user, $channel, OtpContext::Login, $request->code);

        if ($response = $this->respondToVerification($user, $channel, OtpContext::Login, $status)) {
            return $response;
        }

        event(new LoginSucceeded($user, 'otp'));

        return $this->authenticate($request, $user);
    }

    public function loginWithPassword(LoginWithPasswordRequest $request): JsonResponse
    {
        $user = $this->authService->resolveUser($request->identifier);

        if (! $user) {
            event(new LoginFailed($request->identifier, 'user_not_found'));

            return response()->json([
                'message' => __('auth.failed'),
            ], 401);
        }

        $inactive = $this->checkInactive($user);
        if ($inactive) {
            return $inactive;
        }

        $locked = $this->checkLocked($user);
        if ($locked) {
            return $locked;
        }

        if (! Hash::check($request->password, $user->password)) {
            $this->authService->hitFailedAttempt($user);

            event(new LoginFailed($request->identifier, 'invalid_password'));

            return response()->json([
                'message' => __('auth.failed'),
            ], 401);
        }

        $this->authService->clearFailedAttempts($user);

        event(new LoginSucceeded($user, 'password'));

        return $this->authenticate($request, $user);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($request->hasSession()) {
            auth('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        } else {
            $user->currentAccessToken()?->delete();
        }

        event(new LogoutSucceeded($user));

        app('auth')->forgetGuards();

        return response()->json(['message' => __('auth.logout')]);
    }

    public function me(Request $request): JsonResponse|UserResource
    {
        $user = $request->user();

        if (! $user->is_active) {
            return response()->json([
                'message' => __('auth.inactive'),
            ], 401);
        }

        $user->load(['roles', 'activeRole', 'employee']);

        return new UserResource($user);
    }

    public function authorization(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['message' => __('auth.unauthorized')], 401);
        }

        return response()->json([
            'data' => $this->authorizationService->effectivePermissions($user),
        ]);
    }

    private function authenticate(Request $request, User $user): JsonResponse
    {
        if ($request->hasSession()) {
            auth()->login($user);
            $request->session()->regenerate();

            return response()->json([
                'user' => new UserResource($user->load(['roles', 'activeRole', 'employee'])),
            ]);
        }

        $token = $this->authService->createToken($user, $request->ip(), $request->userAgent());

        return response()->json([
            'user' => new UserResource($user->load(['roles', 'activeRole', 'employee'])),
            'token' => $token,
        ]);
    }

    /**
     * Resolve OTP copy strings against the auth namespace.
     *
     * @param  array<string, int|string>  $replace
     */
    protected function otpLang(string $key, array $replace = []): string
    {
        return match ($key) {
            'otp_locked' => __('auth.locked', $replace),
            'otp_expired', 'otp_invalid' => __('auth.invalid_otp'),
            default => __("auth.{$key}"),
        };
    }

    private function checkLocked(User $user): ?JsonResponse
    {
        if (! $this->authService->isLocked($user)) {
            return null;
        }

        $seconds = $this->authService->lockoutSeconds($user);

        return response()->json([
            'message' => __('auth.locked', ['seconds' => $seconds]),
            'retry_after' => $seconds,
        ], 429);
    }

    private function checkInactive(User $user): ?JsonResponse
    {
        if ($user->is_active) {
            return null;
        }

        return response()->json([
            'message' => __('auth.inactive'),
        ], 403);
    }
}
