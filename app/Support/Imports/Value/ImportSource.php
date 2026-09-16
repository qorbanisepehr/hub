<?php

namespace App\Support\Imports\Value;

use Illuminate\Http\UploadedFile;

/**
 * The uploaded bytes, already materialized to a local path. Controllers
 * spool the HTTP upload (Laravel's UploadedFile::store or an explicit temp
 * file); the kernel only ever reads from the filesystem, so readers stay
 * streamable and testable without HTTP.
 */
final class ImportSource
{
    /**
     * @param  string  $path  Local filesystem path to the uploaded file.
     * @param  string  $originalName  Client-provided filename (reports, PII-masking rules).
     * @param  string  $format  Short format name, e.g. 'csv' or 'xlsx' — the
     *                          ReaderRegistry key; do not infer it from the extension.
     */
    public function __construct(
        public readonly string $path,
        public readonly string $originalName,
        public readonly string $format,
    ) {}

    public static function fromUploaded(UploadedFile $file, string $format): self
    {
        $path = $file->getRealPath() ?: throw new \InvalidArgumentException('The upload has no readable backing file.');

        return new self($path, $file->getClientOriginalName(), $format);
    }
}
