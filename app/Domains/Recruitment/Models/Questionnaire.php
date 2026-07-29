<?php

namespace App\Domains\Recruitment\Models;

use App\Contracts\Documentable;
use App\Contracts\DocumentableTrait;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Questionnaire extends Model implements Documentable
{
    use DocumentableTrait;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'status',
        // Real columns: identity
        'first_name',
        'last_name',
        'national_id',
        'gender',
        'birth_date',
        // Real columns: contact
        'email',
        'mobile',
        'phone',
        'emergency_phone',
        // Real columns: marital
        'marital_status',
        'military_status',
        // Real columns: boolean filters
        'has_chronic_disease',
        'has_major_surgery',
        'has_disability',
        'can_travel',
        'has_criminal_record',
        // Real columns: employment
        'employment_type',
        'expected_monthly_salary',
        'minimum_hours_per_month',
        'expected_hourly_salary',
        'submitted_resume_before',
        'interviewed_before',
        'currently_employed',
        'available_start_date',
        // JSONB sections
        'section_personal',
        'section_contact_address',
        'section_military_details',
        'section_spouse',
        'section_education',
        'section_work_experience',
        'section_skills',
        'section_training',
        'section_additional_info',
        'section_job_request',
        'section_documents_metadata',
        // OTP
        'mobile_otp',
        'mobile_verified_at',
        'email_otp',
        'email_verified_at',
        // Meta
        'reviewed_by',
        'version',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'has_chronic_disease' => 'boolean',
        'has_major_surgery' => 'boolean',
        'has_disability' => 'boolean',
        'can_travel' => 'boolean',
        'has_criminal_record' => 'boolean',
        'submitted_resume_before' => 'boolean',
        'interviewed_before' => 'boolean',
        'currently_employed' => 'boolean',
        'expected_monthly_salary' => 'integer',
        'minimum_hours_per_month' => 'integer',
        'expected_hourly_salary' => 'integer',
        'version' => 'integer',
        'mobile_verified_at' => 'datetime',
        'email_verified_at' => 'datetime',
        // JSONB sections
        'section_personal' => 'array',
        'section_contact_address' => 'array',
        'section_military_details' => 'array',
        'section_spouse' => 'array',
        'section_education' => 'array',
        'section_work_experience' => 'array',
        'section_skills' => 'array',
        'section_training' => 'array',
        'section_additional_info' => 'array',
        'section_job_request' => 'array',
        'section_documents_metadata' => 'array',
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

    public function getDocumentConfigKey(): ?string
    {
        return 'recruitment';
    }

    // ── Status helpers ──

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

    // ── OTP helpers ──

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

    // ── Section accessors ──

    public function getSection(string $name): ?array
    {
        return $this->{"section_{$name}"} ?? null;
    }

    public function setSection(string $name, array $data): void
    {
        $this->{"section_{$name}"} = $data;
    }

    // ── Optimistic locking ──

    public function incrementVersion(): void
    {
        $this->increment('version');
    }

    public function matchesVersion(int $expectedVersion): bool
    {
        return $this->version === $expectedVersion;
    }
}
