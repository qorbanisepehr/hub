<?php

namespace App\Support\Exports;

use App\Support\Exports\Value\ExportFile;
use LogicException;

/**
 * One file going into a {@see BundleBuilder} archive.
 *
 * Deliberately dumb: a path on local disk, the name it should take inside the
 * archive, and an optional folder. It knows nothing about what the file IS —
 * not a Document, not a rendered sheet, not a scan — because the kernel builds
 * archives from files and every domain is free to describe its own as these.
 * That is the whole reason a board meeting's bundle and an employee document
 * library's bundle can share one zip implementation.
 *
 * `temporary` is an ownership statement, not a hint. A rendered export only
 * exists as a stream, so putting one in a bundle means materialising it on disk;
 * whoever created that temp file wants it gone afterwards, and saying so here is
 * what lets the builder clean up after itself without guessing which sources
 * are real and which are scratch.
 */
final class BundleEntry
{
    private function __construct(
        public readonly string $entryName,
        public readonly string $sourcePath,
        public readonly ?string $folder = null,
        public readonly bool $temporary = false,
    ) {}

    /**
     * A file that belongs to somebody and must outlive the archive — a stored
     * document on a disk, a row's attachment.
     */
    public static function fromFile(string $path, string $entryName, ?string $folder = null): self
    {
        return new self(
            entryName: $entryName,
            sourcePath: $path,
            folder: $folder,
            temporary: false,
        );
    }

    /**
     * A rendered export — a PDF or Word sheet the document kernel just produced.
     *
     * The kernel hands those over as a stream, because in-memory rendering is
     * what it does everywhere else; ZipArchive needs a path, so the bytes are
     * spilled to a temp file here and marked for cleanup.
     */
    public static function fromExport(ExportFile $export, string $entryName, ?string $folder = null): self
    {
        $path = tempnam(sys_get_temp_dir(), 'export-bundle-');

        if ($path === false) {
            throw new LogicException('Unable to create a temporary file for the export bundle.');
        }

        $handle = fopen($path, 'w');

        if ($handle === false) {
            @unlink($path);

            throw new LogicException('Unable to open the export bundle temporary file.');
        }

        try {
            $export->copyTo($handle);
        } finally {
            fclose($handle);
        }

        return new self(
            entryName: $entryName,
            sourcePath: $path,
            folder: $folder,
            temporary: true,
        );
    }

    /**
     * The entry's name inside the archive, folder included.
     */
    public function archivePath(): string
    {
        return $this->folder === null || trim($this->folder) === ''
            ? $this->entryName
            : rtrim($this->folder, '/').'/'.$this->entryName;
    }
}
