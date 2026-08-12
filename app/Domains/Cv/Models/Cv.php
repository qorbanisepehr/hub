<?php

namespace App\Domains\Cv\Models;

use App\Casts\MobileNumberCast;
use App\Contracts\Documentable;
use App\Contracts\DocumentableTrait;
use App\Contracts\OtpVerifiable;
use App\Domains\Cv\Enums\CvStatus;
use App\Domains\Questionnaire\Models\Questionnaire;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Cv extends Model implements Documentable, OtpVerifiable
{
    use DocumentableTrait;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'status',
        // Real columns: identity
        'first_name',
        'last_name',
        // Real columns: contact
        'email',
        'mobile',
        // JSONB sections
        'section_personal',
        'section_contact_address',
        'section_education',
        'section_work_experience',
        'section_skills',
        'section_training',
        'section_additional_info',
        // OTP verification status
        'mobile_verified_at',
        'email_verified_at',
        // Meta
        'lifecycle',
        'reviewed_by',
        'version',
    ];

    protected $casts = [
        'status' => CvStatus::class,
        'mobile' => MobileNumberCast::class,
        'section_personal' => 'array',
        'section_contact_address' => 'array',
        'section_education' => 'array',
        'section_work_experience' => 'array',
        'section_skills' => 'array',
        'section_training' => 'array',
        'section_additional_info' => 'array',
        'lifecycle' => 'array',
        'mobile_verified_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'version' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Cv $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });

        static::saving(function (Cv $model): void {
            if (! $model->exists) {
                return;
            }

            if ($model->isDirty('email')) {
                $model->email_verified_at = null;
            }
            if ($model->isDirty('mobile')) {
                $model->mobile_verified_at = null;
            }

            // Military service data only applies to male candidates; whenever
            // gender changes away from male (male → female), drop any orphaned
            // military record from the personal-info section.
            if ($model->isDirty('section_personal')) {
                $sectionPersonal = $model->section_personal ?? [];
                if (($sectionPersonal['gender'] ?? null) !== 'مرد'
                    && array_key_exists('military_status', $sectionPersonal)
                    && $sectionPersonal['military_status'] !== null) {
                    unset($sectionPersonal['military_status']);
                    $model->section_personal = $sectionPersonal;
                }
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return HasOne<Questionnaire, $this> */
    public function questionnaire(): HasOne
    {
        return $this->hasOne(Questionnaire::class, 'cv_id');
    }

    public function getDocumentConfigKey(): ?string
    {
        return 'cv';
    }

    // ── Status helpers ──

    public function isDraft(): bool
    {
        return $this->status === CvStatus::Draft;
    }

    public function isSubmitted(): bool
    {
        return $this->status === CvStatus::Submitted;
    }

    public function isApproved(): bool
    {
        return $this->status === CvStatus::Approved;
    }

    public function isRejected(): bool
    {
        return $this->status === CvStatus::Rejected;
    }

    /**
     * Statuses the candidate can still open and edit the form in.
     */
    public function isEditable(): bool
    {
        return $this->isDraft() || $this->isRejected();
    }

    // ── OTP helpers ──

    public function isMobileVerified(): bool
    {
        return $this->isOtpVerified('mobile');
    }

    public function isEmailVerified(): bool
    {
        return $this->isOtpVerified('email');
    }

    /**
     * A CV is fully verified when the mobile is verified and, whenever an
     * email is present, that email is verified too. Email stays optional on
     * a CV, but once filled in it must be confirmed before submitting.
     */
    public function isFullyVerified(): bool
    {
        if (! $this->isMobileVerified()) {
            return false;
        }

        return blank($this->email) || $this->isEmailVerified();
    }

    // ── OtpVerifiable ──

    public function getOtpIdentifier(): string
    {
        return "cv:{$this->uuid}";
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

    // ── Section accessors ──

    public function getSection(string $name): ?array
    {
        return $this->{"section_{$name}"} ?? null;
    }

    public function setSection(string $name, array $data): void
    {
        $this->{"section_{$name}"} = $data;
    }

    // ── Lifecycle ──

    /**
     * Append a lifecycle event (submitted/approved/rejected) keeping the full
     * history ordered by occurrence. The latest event reflects the current
     * review state; older entries remain for reference.
     *
     * @param  array<string, mixed>  $event
     */
    public function recordLifecycleEvent(array $event): void
    {
        $lifecycle = $this->lifecycle ?? [];
        $lifecycle[] = $event;

        $this->update(['lifecycle' => $lifecycle]);
    }

    public function lastLifecycleEvent(): ?array
    {
        $lifecycle = $this->lifecycle ?? [];

        return $lifecycle[array_key_last($lifecycle)] ?? null;
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
