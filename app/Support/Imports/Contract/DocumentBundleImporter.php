<?php

namespace App\Support\Imports\Contract;

use App\Domains\Document\Models\Document;
use App\Support\Imports\Value\DocumentImportResolution;
use App\Support\Imports\Value\DocumentImportTarget;
use Illuminate\Http\UploadedFile;

/**
 * The document-bundle arm of the import kernel: a bulk of scanned files
 * whose filenames carry a user-defined contract (the pattern) mapping each
 * file onto the record it lands on. A sibling of {@see ImportDefinition} —
 * tabular imports stay spreadsheet-shaped; this contract carries per-file
 * statuses (ready / pattern / no_meeting / duplicate) through the same
 * dry-run → confirm → report flow. Entity knowledge — how the parsed parts
 * resolve, what makes a duplicate, and where files land — lives in the
 * definition (OCP), never in the kernel or the controller.
 */
interface DocumentBundleImporter
{
    /**
     * The registry key (e.g. 'board-meeting-documents') — routes and the
     * UI address the entity by it.
     */
    public function name(): string;

    /**
     * Human label for the entity picker and catalogs (translation-driven).
     */
    public function label(): string;

    /**
     * The pattern proposed when the client sends none. The admin may edit
     * the pattern per run; the meaning of its named groups is the contract.
     */
    public function defaultPattern(): string;

    /**
     * The per-batch file cap for this entity's documents.
     */
    public function maxFiles(): int;

    /**
     * Turn the parsed filename parts into a target, or an orphan reason
     * (e.g. no meeting exists on the carried date).
     *
     * @param  array<string, string>  $parts  The pattern's named groups (number, ordinal, date, …).
     */
    public function resolve(array $parts): DocumentImportResolution;

    /**
     * The dedupe identity of a target — the kernel uses it both against
     * the store and to flag two files in one batch claiming the same spot.
     */
    public function dedupeKey(DocumentImportTarget $target): string;

    /**
     * Whether the resolution already exists in the system (a duplicate).
     */
    public function targetExists(DocumentImportTarget $target): bool;

    /**
     * Persist a file onto its resolved target; throws on write failure so
     * the kernel can report the file rather than drop it silently.
     */
    public function persist(UploadedFile $file, DocumentImportTarget $target): Document;

    /**
     * The serving route the imported payloads' urls point at.
     */
    public function serveRouteName(): string;
}
