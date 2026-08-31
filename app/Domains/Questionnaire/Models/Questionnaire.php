<?php

namespace App\Domains\Questionnaire\Models;

use App\Casts\MobileNumberCast;
use App\Contracts\Documentable;
use App\Contracts\DocumentableTrait;
use App\Contracts\OtpVerifiable;
use App\Models\Traits\HasJsonSections;
use App\Models\Traits\HasLifecycleVersion;
use App\Models\Traits\VerifiesOtp;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Questionnaire extends Model implements Documentable, OtpVerifiable
{
    use DocumentableTrait;
    use HasJsonSections;
    use HasLifecycleVersion;
    use SoftDeletes;
    use VerifiesOtp;

    protected $fillable = [
        'uuid',
        'status',
        'cv_id',
        // Real columns: identity
        'first_name',
        'last_name',
        'id_number',
        'gender',
        'birth_date',
        // Real columns: contact
        'email',
        'mobile',
        'phone',
        'emergency_phone',
        // Real columns: marital
        'marital_status',
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
        'section_education',
        'section_work_experience',
        'section_skills',
        'section_training',
        'section_additional_info',
        'section_job_request',
        // OTP verification status
        'mobile_verified_at',
        'email_verified_at',
        // Meta
        'reviewed_by',
        'version',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'mobile' => MobileNumberCast::class,
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
        'section_education' => 'array',
        'section_work_experience' => 'array',
        'section_skills' => 'array',
        'section_training' => 'array',
        'section_additional_info' => 'array',
        'section_job_request' => 'array',
    ];

    /**
     * Military service data only applies to male candidates; whenever gender
     * changes away from male (male → female), drop any orphaned military
     * record from the personal-info section. On a questionnaire, gender is a
     * real column, so this runs when that column changes.
     */
    protected function handleSectionPersistence(): void
    {
        if ($this->isDirty('gender') && $this->gender !== 'male') {
            $this->pruneNonMaleMilitaryStatus();
        }
    }

    protected function otpIdentifierPrefix(): string
    {
        return 'questionnaire';
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getDocumentConfigKey(): ?string
    {
        return 'questionnaire';
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

    public function isFullyVerified(): bool
    {
        return $this->isMobileVerified() && $this->isEmailVerified();
    }
}
