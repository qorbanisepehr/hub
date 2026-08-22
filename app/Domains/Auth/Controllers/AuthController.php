<?php

namespace App\Domains\Auth\Controllers;

use App\Contracts\Authorization;
use App\Domains\Audit\Services\AuditEventDispatcher;
use App\Domains\Auth\Events\LoginFailed;
use App\Domains\Auth\Events\LoginSucceeded;
use App\Domains\Auth\Events\LogoutSucceeded;
use App\Domains\Auth\Requests\LoginRequest;
use App\Domains\Auth\Requests\LoginWithPasswordRequest;
use App\Domains\Auth\Requests\VerifyOtpRequest;
use App\Domains\Auth\Resources\UserResource;
use App\Enums\OtpContext;
use App\Http\Responses\OtpResponder;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController
{
    use OtpResponder;

    public function __construct(
        private OtpService $otpService,
        private Authorization $authorizationService,
        private AuditEventDispatcher $audit,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->resolveUser($request->identifier);

        if (! $user) {
            $this->audit->record(new LoginFailed($request->identifier, 'user_not_found'));

            return response()->json([
                'message' => __('auth.failed'),
            ], 401);
        }

        if ($inactive = $this->checkInactive($user)) {
            return $inactive;
        }

        $channel = $this->channelFor($request->identifier);

        $status = $this->otpService->sendWithCooldown($user, $channel, OtpContext::Login);

        $response = $this->respondToSend($user, $channel, OtpContext::Login, $status);

        $data = $response->getData(true);
        $data['destination'] = $this->destination($request->identifier, $user);

        return response()->json($data, $response->getStatusCode());
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $user = $this->resolveUser($request->identifier);

        if (! $user) {
            return response()->json([
                'message' => __('auth.invalid_otp'),
            ], 422);
        }

        if ($inactive = $this->checkInactive($user)) {
            return $inactive;
        }

        $channel = $this->channelFor($request->identifier);
        $status = $this->otpService->attemptVerification($user, $channel, OtpContext::Login, $request->code);

        if ($response = $this->respondToVerification($user, $channel, OtpContext::Login, $status)) {
            return $response;
        }

        $this->audit->record(new LoginSucceeded($user, 'otp'));

        return $this->authenticate($request, $user);
    }

    public function loginWithPassword(LoginWithPasswordRequest $request): JsonResponse
    {
        $user = $this->resolveUser($request->identifier);

        if (! $user) {
            $this->audit->record(new LoginFailed($request->identifier, 'user_not_found'));

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
            RateLimiter::hit($this->rateLimiterKey($user), config('rate-limits.auth-attempts.period', 60));

            $this->audit->record(new LoginFailed($request->identifier, 'invalid_password'));

            return response()->json([
                'message' => __('auth.failed'),
            ], 401);
        }

        RateLimiter::clear($this->rateLimiterKey($user));

        $this->audit->record(new LoginSucceeded($user, 'password'));

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
            $user->currentAccessToken()->delete();
        }

        if ($user) {
            $this->audit->record(new LogoutSucceeded($user));
        }

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

        $token = $this->createToken($user, $request);

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

    private function channelFor(string $identifier): string
    {
        return filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false ? 'email' : 'mobile';
    }

    private function destination(string $identifier, User $user): string
    {
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false && $user->email) {
            $at = strpos($user->email, '@');

            return substr_replace($user->email, '***', 1, max(1, $at - 2));
        }

        if ($user->phone) {
            return substr_replace($user->phone, '***', 3, 4);
        }

        return $user->email ?: $identifier;
    }

    private function rateLimiterKey(User $user): string
    {
        return 'login-attempts:'.$user->id;
    }

    private function resolveUser(string $identifier): ?User
    {
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return User::where('email', $identifier)->first();
        }

        if (preg_match('/^(\+?\d{10,15})$/', $identifier)) {
            return User::where('phone', $identifier)->first();
        }

        return User::where('username', $identifier)->first();
    }

    private function createToken(User $user, Request $request): string
    {
        $userAgent = $request->userAgent() ?? 'unknown';
        $timestamp = now()->format('YmdHis');
        $uniquePart = Str::random(8);

        $tokenName = sprintf(
            '%s:%s(%s)',
            $request->ip(),
            substr(md5($userAgent.$uniquePart), 0, 12),
            $timestamp,
        );

        return $user->createToken(name: $tokenName)->plainTextToken;
    }

    private function checkLocked(User $user): ?JsonResponse
    {
        $key = $this->rateLimiterKey($user);
        $maxAttempts = config('rate-limits.auth-attempts.limit', 5);

        if (! RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return null;
        }

        $seconds = RateLimiter::availableIn($key);

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
