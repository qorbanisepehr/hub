<?php

namespace App\Support\Exports;

/**
 * A finished archive sitting on a temp file, waiting to be streamed.
 *
 * A value rather than a bare array because the file is the caller's to dispose
 * of and forgetting to is how /tmp fills up: {@see delete()} is right there
 * next to the path, and `BundleBuilder` never keeps a reference afterwards.
 */
final class BundleFile
{
    public function __construct(
        public readonly string $path,
        public readonly string $filename,
        public readonly string $mimeType,
        /** @var int Number of entries that made it into the archive. */
        public readonly int $entryCount,
    ) {}

    public function delete(): void
    {
        if (is_file($this->path)) {
            @unlink($this->path);
        }
    }
}
