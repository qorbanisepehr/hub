<?php

namespace App\Support\Exports;

use RuntimeException;

/**
 * A named source that was not on disk when the archive was built.
 *
 * A kernel exception on purpose: the builder cannot name the model that is
 * missing, because it never knew it. A domain adapter catches this and decides
 * what it means there — a document endpoint answers 404, and so should a meeting
 * — but the decision is made where the knowledge is.
 *
 * Bundling refuses to continue rather than skip the gap. An archive that quietly
 * omits a file looks exactly like a complete one, and "here is your meeting,
 * minus the one scan that was missing from disk" is the kind of archive nobody
 * discovers until it matters.
 */
final class MissingBundleSource extends RuntimeException
{
    private function __construct(
        public readonly string $entryName,
        public readonly string $sourcePath,
    ) {
        parent::__construct("Bundle source is missing on disk: {$sourcePath}");
    }

    public static function forEntry(BundleEntry $entry): self
    {
        return new self(
            entryName: $entry->archivePath(),
            sourcePath: $entry->sourcePath,
        );
    }
}
