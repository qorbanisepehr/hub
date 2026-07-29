<?php

namespace App\Domains\Document\Models;

use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'original_name',
    'mime_type',
    'size',
    'disk',
    'path',
    'hash',
])]
#[UseFactory(DocumentFactory::class)]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    use SoftDeletes;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Document $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /** @return HasMany<DocumentUsage, $this> */
    public function usages(): HasMany
    {
        return $this->hasMany(DocumentUsage::class);
    }

    /** @return HasMany<Revision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(Revision::class);
    }

    public function scopeForEntity(Builder $query, string $entityType, int $entityId): Builder
    {
        return $query->whereHas('usages', function ($q) use ($entityType, $entityId) {
            $q->where('entity_type', $entityType)
                ->where('entity_id', $entityId);
        });
    }

    public function scopeByCategory(Builder $query, string $categorySlug): Builder
    {
        return $query->whereHas('usages', function ($q) use ($categorySlug) {
            $q->where('category_slug', $categorySlug);
        });
    }

    public function scopeByRecordKey(Builder $query, string $recordKey): Builder
    {
        return $query->whereHas('usages', function ($q) use ($recordKey) {
            $q->where('record_key', $recordKey);
        });
    }

    public function getFullUrl(): string
    {
        return \Storage::disk($this->disk)->url($this->path);
    }
}
