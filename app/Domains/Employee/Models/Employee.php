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
}
