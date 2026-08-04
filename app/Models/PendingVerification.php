<?php

namespace App\Models;

use App\Casts\MobileNumberCast;
use App\Contracts\OtpVerifiable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PendingVerification extends Model implements OtpVerifiable
{
    protected $fillable = [
        'uuid',
        'type',
        'mobile',
        'email',
        'payload',
        'verified_at',
    ];

    protected $casts = [
        'mobile' => MobileNumberCast::class,
        'payload' => 'array',
        'verified_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (PendingVerification $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function markVerified(): void
    {
        $this->update(['verified_at' => now()]);
    }

    // ── OtpVerifiable ──

    public function getOtpIdentifier(): string
    {
        return "pending-verification:{$this->uuid}";
    }

    public function markOtpVerified(string $channel): void
    {
        $this->update(['verified_at' => now()]);
    }

    public function isOtpVerified(string $channel): bool
    {
        return $this->verified_at !== null;
    }
}
