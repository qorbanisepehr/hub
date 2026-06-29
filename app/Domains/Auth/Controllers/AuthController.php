<?php

namespace App\Domains\Auth\Controllers;

use App\Domains\Auth\Requests\LoginRequest;
use App\Domains\Auth\Requests\LoginWithPasswordRequest;
use App\Domains\Auth\Requests\VerifyOtpRequest;
use App\Domains\Auth\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController
{
    private const int OTP_EXPIRY_MINUTES = 5;

    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->resolveUser($request->identifier);

        if (! $user) {
            return response()->json([
                'message' => __('auth.failed'),
            ], 401);
        }

        $locked = $this->checkLocked($user);
        if ($locked) {
            return $locked;
        }

        $code = (string) random_int(100000, 999999);
        $isEmail = filter_var($request->identifier, FILTER_VALIDATE_EMAIL) !== false;

        $user->update([
            'otp_code' => Hash::make($code),
            'otp_expires_at' => now()->addMinutes(self::OTP_EXPIRY_MINUTES),
        ]);

        if (app()->environment('local', 'testing')) {
            Log::info('OTP for user {user}: {code}', ['user' => $user->email, 'code' => $code]);
        }

        return response()->json([
            'message' => __('auth.otp_sent'),
            'destination' => $isEmail
                ? substr_replace($user->email, '***', 1, strpos($user->email, '@') - 2)
                : substr_replace($user->phone, '***', 3, 4),
        ]);
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $user = $this->resolveUser($request->identifier);

        if (! $user || ! $user->otp_code || $user->otp_expires_at?->isPast()) {
            return response()->json([
                'message' => __('auth.invalid_otp'),
            ], 422);
        }

        $locked = $this->checkLocked($user);
        if ($locked) {
            return $locked;
        }

        if (! Hash::check($request->code, $user->otp_code)) {
            RateLimiter::hit($this->rateLimiterKey($user), config('rate-limits.auth-attempts.period', 60));

            return response()->json([
                'message' => __('auth.invalid_otp'),
            ], 422);
        }

        $user->update([
            'otp_code' => null,
            'otp_expires_at' => null,
        ]);

        RateLimiter::clear($this->rateLimiterKey($user));

        return response()->json([
            'user' => new UserResource($user),
            'token' => $this->createToken($user, $request),

        ]);
    }

    public function loginWithPassword(LoginWithPasswordRequest $request): JsonResponse
    {
        $user = $this->resolveUser($request->identifier);

        if (! $user) {
            return response()->json([
                'message' => __('auth.failed'),
            ], 401);
        }

        $locked = $this->checkLocked($user);
        if ($locked) {
            return $locked;
        }

        if (! Hash::check($request->password, $user->password)) {
            RateLimiter::hit($this->rateLimiterKey($user), config('rate-limits.auth-attempts.period', 60));

            return response()->json([
                'message' => __('auth.failed'),
            ], 401);
        }

        RateLimiter::clear($this->rateLimiterKey($user));

        return response()->json([
            'user' => new UserResource($user),
            'token' => $this->createToken($user, $request),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => __('auth.logout')]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
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
}
