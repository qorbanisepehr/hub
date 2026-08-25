<?php

namespace App\Domains\Employee\Models;

use App\Contracts\Documentable;
use App\Contracts\DocumentableTrait;
use App\Models\User;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'personnel_code',
    'first_name',
    'last_name',
    'gender',
    'birth_date',
    'id_number',
    'marital_status',
    'email',
    'mobile',
    'social_insurance_number',
    'employment_type',
    'hire_date',
    'employment_status',
    'user_id',
    'section_personal',
    'section_contact_address',
    'section_education',
    'section_work_experience',
    'section_skills',
    'section_training',
    'section_additional_info',
    'section_social_insurance',
    'section_dependents',
    'section_document_inquiries',
])]
#[UseFactory(EmployeeFactory::class)]
class Employee extends Model implements Documentable
{
    use DocumentableTrait;

    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, SoftDeletes;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'hire_date' => 'date',
            'section_personal' => 'array',
            'section_contact_address' => 'array',
            'section_education' => 'array',
            'section_work_experience' => 'array',
            'section_skills' => 'array',
            'section_training' => 'array',
            'section_additional_info' => 'array',
            'section_social_insurance' => 'array',
            'section_dependents' => 'array',
            'section_document_inquiries' => 'array',
        ];
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

    /**
     * Degree and field of the latest education record, for compact
     * eligibility previews.
     *
     * @return array{degree: ?string, field_of_study: ?string}
     */
    public function latestEducation(): array
    {
        $records = $this->section_education['education_records'] ?? [];
        $latest = is_array($records) ? end($records) : null;

        return [
            'degree' => is_array($latest) ? ($latest['degree'] ?? null) : null,
            'field_of_study' => is_array($latest) ? ($latest['field'] ?? null) : null,
        ];
    }

    /**
     * Whole years since hire date — organizational seniority.
     */
    public function orgTenureYears(): ?int
    {
        if ($this->hire_date === null) {
            return null;
        }

        return (int) floor($this->hire_date->diffInYears(now()));
    }

    // ── Documentable ──

    /**
     * The personnel code travels with every document name so saved or
     * downloaded files stay attributable to their owner.
     */
    public function getDocumentOwnerLabel(): ?string
    {
        $code = trim((string) $this->personnel_code);

        return $code !== '' ? $code : null;
    }
}
