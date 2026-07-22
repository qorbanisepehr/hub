<?php

namespace App\Domains\Recruitment\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Questionnaire extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'status',
        'current_step',
        'first_name',
        'last_name',
        'email',
        'mobile',
        'personal_info',
        'education',
        'work_experience',
        'skills',
        'training',
        'additional_info',
        'job_request',
        'review',
        'mobile_otp',
        'mobile_verified_at',
        'email_otp',
        'email_verified_at',
        'reviewed_by',
    ];

    protected $casts = [
        'current_step' => 'integer',
        'personal_info' => 'array',
        'education' => 'array',
        'work_experience' => 'array',
        'skills' => 'array',
        'training' => 'array',
        'additional_info' => 'array',
        'job_request' => 'array',
        'review' => 'array',
        'mobile_verified_at' => 'datetime',
        'email_verified_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Questionnaire $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    public function isReviewed(): bool
    {
        return $this->status === 'reviewed';
    }

    public function isMobileVerified(): bool
    {
        return $this->mobile_verified_at !== null;
    }

    public function isEmailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function isFullyVerified(): bool
    {
        return $this->isMobileVerified() && $this->isEmailVerified();
    }

    public static function generateOtp(): string
    {
        return (string) random_int(100000, 999999);
    }
}
