<?php

namespace App\Services;

use App\Contracts\OtpVerifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class OtpService
{
    private function cacheKey(OtpVerifiable $entity, string $channel): string
    {
        return "otp:{$entity->getOtpIdentifier()}:{$channel}";
    }

    public function send(OtpVerifiable $entity, string $channel): string
    {
        $code = (string) random_int(100000, 999999);
        $ttl = config('rate-limits.recruitment-otp-send.ttl', 120);
        $expiresAt = now()->addSeconds($ttl);

        Cache::put(
            $this->cacheKey($entity, $channel),
            [
                'hash' => Hash::make($code),
                'expires_at' => $expiresAt->timestamp,
            ],
            $expiresAt,
        );

        if (app()->isLocal() || app()->environment('testing')) {
            Log::info("OTP ({$channel}) for {$entity->getOtpIdentifier()}: {$code}");
        }

        return $code;
    }

    public function verify(OtpVerifiable $entity, string $channel, string $code): bool
    {
        $data = Cache::get($this->cacheKey($entity, $channel));

        if (! $data || ! Hash::check($code, $data['hash'])) {
            return false;
        }

        Cache::forget($this->cacheKey($entity, $channel));

        return true;
    }

    public function isLocked(OtpVerifiable $entity, string $channel): bool
    {
        $key = $this->attemptsKey($entity, $channel);
        $limit = config('rate-limits.recruitment-otp-verify.limit', 5);

        return RateLimiter::tooManyAttempts($key, $limit);
    }

    public function getLockoutSeconds(OtpVerifiable $entity, string $channel): int
    {
        return RateLimiter::availableIn($this->attemptsKey($entity, $channel));
    }

    public function isExpired(OtpVerifiable $entity, string $channel): bool
    {
        return ! Cache::has($this->cacheKey($entity, $channel));
    }

    public function hitFailedAttempt(OtpVerifiable $entity, string $channel): void
    {
        RateLimiter::hit(
            $this->attemptsKey($entity, $channel),
            config('rate-limits.recruitment-otp-verify.period', 300),
        );
    }

    public function clearFailedAttempts(OtpVerifiable $entity, string $channel): void
    {
        RateLimiter::clear($this->attemptsKey($entity, $channel));
    }

    public function getExpiresIn(OtpVerifiable $entity, string $channel): int
    {
        $data = Cache::get($this->cacheKey($entity, $channel));

        if (! $data) {
            return 0;
        }

        return max(0, (int) round($data['expires_at'] - now()->timestamp));
    }

    private function attemptsKey(OtpVerifiable $entity, string $channel): string
    {
        return "otp-attempts:{$entity->getOtpIdentifier()}:{$channel}";
    }
}
