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
     * Build a masked destination (email or phone) for the send response,
     * based on the channel the user chose rather than the identifier shape.
     */
    public function destination(string $channel, User $user): string
    {
        if ($channel === 'email' && $user->email) {
            return $this->maskEmail($user->email);
        }

        if ($channel === 'mobile' && $user->phone) {
            return $this->maskPhone($user->phone);
        }

        // Defensive fallback (the controller 422s before this is reachable):
        // mask whichever contact exists so no raw address ever leaks.
        if ($user->phone) {
            return $this->maskPhone($user->phone);
        }

        if ($user->email) {
            return $this->maskEmail($user->email);
        }

        return '';
    }

    private function maskEmail(string $email): string
    {
        $at = strpos($email, '@');

        return substr_replace($email, '***', 1, max(1, $at - 2));
    }

    private function maskPhone(string $phone): string
    {
        return substr_replace($phone, '***', 3, 4);
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
