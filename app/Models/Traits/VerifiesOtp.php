<?php

namespace App\Models\Traits;

/**
 * Drives the OtpVerifiable contract for candidate models (Cv, Questionnaire).
 *
 * The OTP identifier prefix is the one legitimately divergent bit between the
 * models; everything else is identical. The dirty-contact verification reset
 * (mobile_verified_at / email_verified_at cleared whenever email/mobile
 * change) is owned here too, so it is only present on models that actually
 * verify OTP.
 *
 * isFullyVerified() policy differs per model (email is optional on a CV but
 * mandatory on a questionnaire), so that stays on the model.
 */
trait VerifiesOtp
{
    public function getOtpIdentifier(): string
    {
        return "{$this->otpIdentifierPrefix()}:{$this->uuid}";
    }

    public function markOtpVerified(string $channel): void
    {
        $this->update(["{$channel}_verified_at" => now()]);
    }

    public function isOtpVerified(string $channel): bool
    {
        return match ($channel) {
            'mobile' => $this->mobile_verified_at !== null,
            'email' => $this->email_verified_at !== null,
            default => false,
        };
    }

    public function isMobileVerified(): bool
    {
        return $this->isOtpVerified('mobile');
    }

    public function isEmailVerified(): bool
    {
        return $this->isOtpVerified('email');
    }

    public static function bootVerifiesOtp(): void
    {
        static::saving(function ($model): void {
            if (! $model->exists) {
                return;
            }

            if ($model->isDirty('email')) {
                $model->email_verified_at = null;
            }
            if ($model->isDirty('mobile')) {
                $model->mobile_verified_at = null;
            }
        });
    }

    /**
     * Prefix used to build the OTP identifier (e.g. 'cv', 'questionnaire').
     */
    protected function otpIdentifierPrefix(): string
    {
        return 'entity';
    }
}
