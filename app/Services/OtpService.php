<?php

namespace App\Services;

use App\Contracts\OtpVerifiable;
use App\Enums\GrantPurpose;
use App\Enums\OtpContext;
use App\Enums\OtpSendStatus;
use App\Enums\OtpVerifyStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class OtpService
{
    public function send(OtpVerifiable $entity, string $channel, OtpContext $context): string
    {
        $code = $this->generateCode();
        $expiresAt = now()->addSeconds($this->ttl());

        Cache::put(
            $this->cacheKey($entity, $channel, $context),
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

    public function verify(OtpVerifiable $entity, string $channel, OtpContext $context, string $code): bool
    {
        $data = Cache::get($this->cacheKey($entity, $channel, $context));

        if (! $data || ! Hash::check($code, $data['hash'])) {
            return false;
        }

        Cache::forget($this->cacheKey($entity, $channel, $context));

        return true;
    }

    /**
     * Run the full verify sequence (lockout → expiry → code check).
     *
     * On success the failed-attempt counter is cleared; the caller is
     * responsible for any context-specific post-verification work.
     */
    public function attemptVerification(OtpVerifiable $entity, string $channel, OtpContext $context, string $code): OtpVerifyStatus
    {
        if ($this->isLocked($entity, $channel, $context)) {
            return OtpVerifyStatus::Locked;
        }

        if ($this->isExpired($entity, $channel, $context)) {
            return OtpVerifyStatus::Expired;
        }

        if (! $this->verify($entity, $channel, $context, $code)) {
            $this->hitFailedAttempt($entity, $channel, $context);

            return OtpVerifyStatus::Invalid;
        }

        $this->clearFailedAttempts($entity, $channel, $context);

        return OtpVerifyStatus::Success;
    }

    /**
     * Send an OTP unless one is still valid or the entity is locked out.
     */
    public function sendWithCooldown(OtpVerifiable $entity, string $channel, OtpContext $context): OtpSendStatus
    {
        if ($this->isLocked($entity, $channel, $context)) {
            return OtpSendStatus::Locked;
        }

        if (! $this->isExpired($entity, $channel, $context)) {
            return OtpSendStatus::AlreadySent;
        }

        $this->send($entity, $channel, $context);

        return OtpSendStatus::Sent;
    }

    /**
     * Store a staged contact value that should be committed to the entity only
     * after the OTP for that channel has been successfully verified.
     */
    public function storePendingValue(OtpVerifiable $entity, string $channel, OtpContext $context, string $value): void
    {
        Cache::put(
            $this->pendingValueKey($entity, $channel, $context),
            $value,
            now()->addSeconds($this->ttl()),
        );
    }

    /**
     * Pull the staged contact value for a channel, if any. The value is
     * consumed on read so it is committed at most once.
     */
    public function pullPendingValue(OtpVerifiable $entity, string $channel, OtpContext $context): ?string
    {
        $value = Cache::get($this->pendingValueKey($entity, $channel, $context));

        if ($value === null) {
            return null;
        }

        Cache::forget($this->pendingValueKey($entity, $channel, $context));

        return (string) $value;
    }

    public function isLocked(OtpVerifiable $entity, string $channel, OtpContext $context): bool
    {
        $key = $this->attemptsKey($entity, $channel, $context);
        $limit = config('otp.attempts.limit', 5);

        return RateLimiter::tooManyAttempts($key, $limit);
    }

    public function getLockoutSeconds(OtpVerifiable $entity, string $channel, OtpContext $context): int
    {
        return (int) RateLimiter::availableIn($this->attemptsKey($entity, $channel, $context));
    }

    public function isExpired(OtpVerifiable $entity, string $channel, OtpContext $context): bool
    {
        return ! Cache::has($this->cacheKey($entity, $channel, $context));
    }

    public function hitFailedAttempt(OtpVerifiable $entity, string $channel, OtpContext $context): void
    {
        RateLimiter::hit(
            $this->attemptsKey($entity, $channel, $context),
            config('otp.attempts.period', 300),
        );
    }

    public function clearFailedAttempts(OtpVerifiable $entity, string $channel, OtpContext $context): void
    {
        RateLimiter::clear($this->attemptsKey($entity, $channel, $context));
    }

    public function getExpiresIn(OtpVerifiable $entity, string $channel, OtpContext $context): int
    {
        $data = Cache::get($this->cacheKey($entity, $channel, $context));

        if (! $data) {
            return 0;
        }

        return max(0, (int) round($data['expires_at'] - now()->timestamp));
    }

    /**
     * Issue a short-lived, reusable access grant for the given entity.
     */
    public function issueGrant(
        OtpVerifiable $entity,
        string $channel,
        OtpContext $context,
        GrantPurpose $purpose,
        ?int $ttl = null,
    ): string {
        $ttl ??= $this->grantTtl($purpose);
        $token = Str::random(64);
        $expiresAt = now()->addSeconds($ttl);

        Cache::put(
            $this->grantKey($entity, $channel, $context, $token),
            [
                'purpose' => $purpose->value,
                'expires_at' => $expiresAt->timestamp,
            ],
            $expiresAt,
        );

        return $token;
    }

    /**
     * Redeem an access grant. Grants are reusable until they expire.
     *
     * A grant only satisfies the request when its purpose covers the requested
     * one (see GrantPurpose::covers).
     */
    public function redeemGrant(
        OtpVerifiable $entity,
        string $channel,
        OtpContext $context,
        string $token,
        GrantPurpose $purpose,
    ): bool {
        $data = Cache::get($this->grantKey($entity, $channel, $context, $token));

        if (! is_array($data) || (int) ($data['expires_at'] ?? 0) < now()->timestamp) {
            return false;
        }

        $granted = GrantPurpose::tryFrom((string) ($data['purpose'] ?? ''));

        return $granted !== null && GrantPurpose::covers($granted, $purpose);
    }

    private function generateCode(): string
    {
        $length = config('otp.code_length', 6);
        $min = 10 ** ($length - 1);
        $max = (10 ** $length) - 1;

        return (string) random_int($min, $max);
    }

    private function ttl(): int
    {
        return (int) config('otp.ttl', 120);
    }

    private function grantTtl(GrantPurpose $purpose): int
    {
        return (int) config(
            "otp.grants.purpose_ttl.{$purpose->value}",
            config('otp.grants.ttl', 600),
        );
    }

    /**
     * The lifetime in seconds a newly issued grant for the given purpose gets.
     */
    public function getGrantExpiresIn(GrantPurpose $purpose): int
    {
        return $this->grantTtl($purpose);
    }

    private function cacheKey(OtpVerifiable $entity, string $channel, OtpContext $context): string
    {
        return "otp:{$context->value}:{$entity->getOtpIdentifier()}:{$channel}";
    }

    private function attemptsKey(OtpVerifiable $entity, string $channel, OtpContext $context): string
    {
        return "otp-attempts:{$context->value}:{$entity->getOtpIdentifier()}:{$channel}";
    }

    private function pendingValueKey(OtpVerifiable $entity, string $channel, OtpContext $context): string
    {
        return "otp-pending:{$context->value}:{$entity->getOtpIdentifier()}:{$channel}";
    }

    private function grantKey(OtpVerifiable $entity, string $channel, OtpContext $context, string $token): string
    {
        return "grant:{$context->value}:{$entity->getOtpIdentifier()}:{$channel}:{$token}";
    }
}
