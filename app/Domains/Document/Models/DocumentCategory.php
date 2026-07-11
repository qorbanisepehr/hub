<?php

namespace App\Domains\Document\Models;

use App\Domains\Employee\Models\Employee;
use Database\Factories\DocumentCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'description', 'sort_order', 'documentable_type'])]
#[UseFactory(DocumentCategoryFactory::class)]
class DocumentCategory extends Model
{
    /** @use HasFactory<DocumentCategoryFactory> */
    use HasFactory;

    /** @param Builder<DocumentCategory> $query */
    public function scopeByType(Builder $query, string $type): void
    {
        $query->where('documentable_type', $type);
    }

    /** @return array<string, class-string> */
    public static function allowedTypes(): array
    {
        return [
            'employee' => Employee::class,
        ];
    }

    public static function resolveType(string $type): ?string
    {
        return self::allowedTypes()[$type] ?? null;
    }

    /** @return HasMany<Document, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
