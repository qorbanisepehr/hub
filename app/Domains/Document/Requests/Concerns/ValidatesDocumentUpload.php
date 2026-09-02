<?php

namespace App\Domains\Document\Requests\Concerns;

use Illuminate\Validation\Rules\File;

/**
 * Shared document upload validation for every entity upload request. Provides
 * the file rule (MIME types + size limit from the per-entity document config)
 * and the common metadata/placement field rules so uploads share one source
 * of truth. File::max() expects kilobytes, while the config stores bytes — the
 * conversion happens here so callers never feed raw bytes into the KB-based
 * API.
 */
trait ValidatesDocumentUpload
{
    /**
     * Rules for a document upload, parameterized by entity config key
     * ("cv", "questionnaire", "employee") or null for the global documents.*
     * block.
     *
     * @return array<string, mixed>
     */
    protected function documentUploadRules(
        ?string $configKey = null,
        bool $withPlacement = true,
        bool $withMeta = true,
    ): array {
        $prefix = $configKey !== null ? "documents.{$configKey}" : 'documents';

        $rules = [
            'file' => [
                'required',
                File::default()
                    ->types(config("{$prefix}.allowed_mime_types"))
                    ->max($this->bytesToKilobytes((int) config("{$prefix}.max_file_size"))),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];

        if ($withPlacement) {
            $rules['section_key'] = ['nullable', 'string', 'max:100'];
            $rules['field_key'] = ['nullable', 'string', 'max:100'];
        }

        if ($withMeta) {
            $rules['meta'] = ['nullable', 'json', 'max:5000'];
            $rules['form_data'] = ['nullable', 'json'];
        }

        return $rules;
    }

    private function bytesToKilobytes(int $bytes): int
    {
        return max(1, (int) round($bytes / 1024));
    }
}
