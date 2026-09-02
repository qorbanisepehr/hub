<?php

namespace App\Domains\Auth\Services;

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Handles the identifier → user and channel resolution, destination masking,
 * password-login lockout, and Sanctum token naming for the auth flow.
 *
 * The controller keeps request validation, authorization, session/guard wiring
 * and response shaping; this service owns the reusable auth domain logic.
 */
class AuthService
{
    /**
     * Resolve an email, phone, or username identifier to a user.
     */
    public function resolveUser(string $identifier): ?User
    {
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return User::where('email', $identifier)->first();
        }

        if (preg_match('/^(\+?\d{10,15})$/', $identifier)) {
            return User::where('phone', $identifier)->first();
        }

        return User::where('username', $identifier)->first();
    }

    /**
     * Detect the OTP channel for an identifier (email or mobile).
     */
    public function channelFor(string $identifier): string
    {
        return filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false ? 'email' : 'mobile';
    }

    /**
     * Build a masked destination (email or phone) for the send response.
     */
    public function destination(string $identifier, User $user): string
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

    public function rateLimiterKey(User $user): string
    {
        return 'login-attempts:'.$user->id;
    }

    public function isLocked(User $user): bool
    {
        return RateLimiter::tooManyAttempts(
            $this->rateLimiterKey($user),
            config('rate-limits.auth-attempts.limit', 5),
        );
    }

    public function lockoutSeconds(User $user): int
    {
        return (int) RateLimiter::availableIn($this->rateLimiterKey($user));
    }

    public function hitFailedAttempt(User $user): void
    {
        RateLimiter::hit(
            $this->rateLimiterKey($user),
            config('rate-limits.auth-attempts.period', 60),
        );
    }

    public function clearFailedAttempts(User $user): void
    {
        RateLimiter::clear($this->rateLimiterKey($user));
    }

    /**
     * Create an access token with a human-readable device-oriented name.
     */
    public function createToken(User $user, ?string $ip, ?string $userAgent): string
    {
        $userAgent ??= 'unknown';
        $timestamp = now()->format('YmdHis');
        $uniquePart = Str::random(8);

        $tokenName = sprintf(
            '%s:%s(%s)',
            $ip ?? 'unknown',
            substr(md5($userAgent.$uniquePart), 0, 12),
            $timestamp,
        );

        return $user->createToken(name: $tokenName)->plainTextToken;
    }
}
