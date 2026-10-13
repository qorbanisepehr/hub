<?php

namespace App\Support\Exports;

use RuntimeException;
use ZipArchive;

/**
 * Packs files into a zip — the kernel half of every "download it all" feature.
 *
 * One implementation for the whole hub, and it is here rather than in any domain
 * on purpose: a board meeting's bundle (a rendered sheet plus its scans), an
 * employee document library, a CV's questionnaires all want the same four
 * things — verify the sources, cap them, resolve name collisions, and build to a
 * temp file so nothing is ever buffered in memory. Only the first of those is a
 * judgement a domain should make.
 *
 * What it deliberately does NOT do:
 *
 *  - **Authorize.** Nothing here knows what a permission is. An adapter must
 *    check every entry before handing it over; a builder that trusted its input
 *    would quietly become the reason the next domain forgot to check.
 *  - **Read domain config.** The caps arrive as arguments. Which number is right
 *    is a domain decision (a meeting bundle and a document library have very
 *    different tolerances), and a kernel that read one domain's config file
 *    would make that decision invisibly.
 *  - **Guess at names.** Entries arrive already named. Collisions are resolved
 *    by suffixing, but the caller owns the vocabulary.
 *
 * The archive is built on a temp file and closed before the first byte leaves
 * the process, so a failed build produces no download at all rather than a
 * truncated one that looks finished.
 */
final class BundleBuilder
{
    public const MIME_TYPE = 'application/zip';

    /**
     * Build the archive and return it as an undisposed temp file.
     *
     * @param  list<BundleEntry>  $entries
     * @param  int|null  $maxEntries  Refuse above this count; null for no count cap.
     * @param  int|null  $maxBytes  Refuse above this total; null for no size cap.
     *
     * @throws BundleTooLarge
     * @throws MissingBundleSource
     */
    public function build(
        array $entries,
        string $filename,
        ?int $maxEntries = null,
        ?int $maxBytes = null,
    ): BundleFile {
        if ($maxEntries !== null && count($entries) > $maxEntries) {
            throw BundleTooLarge::forEntries(count($entries), $maxEntries);
        }

        $this->assertWithinBytes($entries, $maxBytes);
        $this->assertSourcesExist($entries);

        $path = tempnam(sys_get_temp_dir(), 'export-bundle-');

        if ($path === false) {
            throw new RuntimeException('Unable to create a temporary file for the export bundle.');
        }

        $zip = new ZipArchive;
        $written = [];

        try {
            if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Unable to open the temporary bundle file.');
            }

            foreach ($entries as $entry) {
                $archivePath = $this->uniqueEntryName($entry->archivePath(), $written);

                $written[] = $archivePath;

                if (! $zip->addFile($entry->sourcePath, $archivePath)) {
                    throw MissingBundleSource::forEntry($entry);
                }
            }

            if (! $zip->close()) {
                throw new RuntimeException('Unable to finalize the temporary bundle file.');
            }
        } catch (\Throwable $error) {
            @$zip->close();
            @unlink($path);

            throw $error;
        } finally {
            // Scratch sources (a rendered sheet spilled to disk for this one
            // archive) are ours to remove; a stored document is not.
            foreach ($entries as $entry) {
                if ($entry->temporary) {
                    @unlink($entry->sourcePath);
                }
            }
        }

        return new BundleFile(
            path: $path,
            filename: $filename,
            mimeType: self::MIME_TYPE,
            entryCount: count($written),
        );
    }

    /**
     * @param  list<BundleEntry>  $entries
     */
    private function assertWithinBytes(array $entries, ?int $maxBytes): void
    {
        if ($maxBytes === null) {
            return;
        }

        $total = 0;

        foreach ($entries as $entry) {
            $total += (int) @filesize($entry->sourcePath);
        }

        if ($total > $maxBytes) {
            throw BundleTooLarge::forBytes($total, $maxBytes);
        }
    }

    /**
     * @param  list<BundleEntry>  $entries
     */
    private function assertSourcesExist(array $entries): void
    {
        foreach ($entries as $entry) {
            if (! is_file($entry->sourcePath)) {
                throw MissingBundleSource::forEntry($entry);
            }
        }
    }

    /**
     * Two attachments of a meeting can share a name; the second must land beside
     * the first rather than overwrite it, or the archive silently loses one.
     *
     * @param  list<string>  $taken
     */
    private function uniqueEntryName(string $entryPath, array $taken): string
    {
        if (! in_array($entryPath, $taken, true)) {
            return $entryPath;
        }

        $dot = strrpos($entryPath, '.');
        $base = $dot === false ? $entryPath : substr($entryPath, 0, $dot);
        $extension = $dot === false ? '' : substr($entryPath, $dot);

        $counter = 2;

        while (in_array("{$base}-{$counter}{$extension}", $taken, true)) {
            $counter++;
        }

        return "{$base}-{$counter}{$extension}";
    }
}
