<?php

namespace App\Domains\Employee\Models;

use App\Contracts\Documentable;
use App\Domains\Document\Models\Document;
use App\Models\User;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'personnel_code',
    'first_name',
    'last_name',
    'gender',
    'birth_date',
    'id_number',
    'marital_status',
    'education_level',
    'education_field',
    'employment_type',
    'hire_date',
    'employment_status',
    'user_id',
])]
#[UseFactory(EmployeeFactory::class)]
class Employee extends Model implements Documentable
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, SoftDeletes;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphMany<Document, $this> */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function getDocumentIdentifier(): string
    {
        return $this->personnel_code;
    }

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'hire_date' => 'date',
        ];
    }
}
