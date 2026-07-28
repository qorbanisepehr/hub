<?php

namespace App\Domains\Document\Models;

use App\Models\User;
use Database\Factories\RevisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'document_id',
    'stored_path',
    'thumbnail_path',
    'original_name',
    'mime_type',
    'file_size',
    'form_data',
    'uploaded_by',
])]
#[UseFactory(RevisionFactory::class)]
class Revision extends Model
{
    /** @use HasFactory<RevisionFactory> */
    use HasFactory;

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function formatFileSize(): string
    {
        $bytes = $this->file_size;

        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'form_data' => 'array',
        ];
    }
}
