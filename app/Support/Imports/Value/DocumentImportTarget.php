<?php

namespace App\Support\Imports\Value;

use App\Contracts\Documentable;

/**
 * Where one parsed filename lands: the documentable record plus the
 * metadata that makes the import meaningful (for board meetings the
 * registry number), and the human-readable derivation the preview renders
 * ("this file → registry number X on the meeting of date Y").
 */
final class DocumentImportTarget
{
    /**
     * @param  array<string, mixed>  $metadata  Document usage metadata (e.g. registry_number).
     * @param  array<string, mixed>  $details  Derived fields for the preview (number, date, meeting label, …).
     */
    public function __construct(
        public readonly Documentable $entity,
        public readonly array $metadata,
        public readonly array $details = [],
    ) {}
}
