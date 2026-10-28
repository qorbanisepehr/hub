<?php

namespace App\Support\Imports;

use App\Domains\Document\Services\DocumentService;
use App\Support\Imports\Contract\DocumentBundleImporter;
use App\Support\Imports\Value\DocumentBundlePlan;
use App\Support\Imports\Value\DocumentImportTarget;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Throwable;

/**
 * The document-bundle import kernel: parse every filename against the
 * user's pattern, resolve each file through the entity's definition, and
 * report per-file statuses (ready / pattern / no_meeting / duplicate). The
 * dry-run writes nothing; confirm re-runs the same resolution and persists
 * the ready files through the definition's persister. A file that fails
 * mid-write is reported, never silently dropped — an import that mostly
 * landed is a normal result.
 */
final class DocumentBundleImportService
{
    public function __construct(private readonly DocumentService $documents) {}

    /**
     * @param  list<UploadedFile>  $files
     */
    public function dryRun(DocumentBundleImporter $definition, array $files, ?string $pattern): DocumentBundlePlan
    {
        $rows = [];

        foreach ($this->resolvedRows($definition, $files, $pattern) as $row) {
            $rows[] = $this->planRow($row);
        }

        return new DocumentBundlePlan($this->normalizedPattern($definition, $pattern), $rows);
    }

    /**
     * Re-validate the same upload and persist the ready files. The report
     * mirrors the per-meeting bulk upload: imported mapping rows (file,
     * resolved meeting, and the landed document payload), skipped and
     * failed files with their reasons.
     *
     * @param  list<UploadedFile>  $files
     * @return array{
     *     imported: list<array{source_file: string, meeting: ?array<string, mixed>, document: array<string, mixed>}>,
     *     skipped: list<array{file: string, reason_code: ?string, reason: ?string}>,
     *     failed: list<array{file: string, reason_code: string, reason: string}>,
     * }
     */
    public function confirm(DocumentBundleImporter $definition, array $files, ?string $pattern): array
    {
        $imported = [];
        $skipped = [];
        $failed = [];

        foreach ($this->resolvedRows($definition, $files, $pattern) as $row) {
            if ($row['status'] !== 'ready') {
                $skipped[] = [
                    'file' => $row['file'],
                    'reason_code' => $row['reason_code'],
                    'reason' => $row['reason'],
                ];

                continue;
            }

            /** @var DocumentImportTarget $target */
            $target = $row['target'];

            try {
                $document = $definition->persist($row['file_object'], $target);
                $usage = $this->documents->newestUsageFor($target->entity, $document->id);
                $imported[] = [
                    'source_file' => $row['file'],
                    'meeting' => $target->details['meeting'] ?? null,
                    'document' => $this->documents->documentPayload(
                        $document,
                        $usage,
                        $definition->serveRouteName(),
                    ),
                ];
            } catch (Throwable $exception) {
                $failed[] = [
                    'file' => $row['file'],
                    'reason_code' => 'error',
                    'reason' => $exception->getMessage(),
                ];
            }
        }

        return compact('imported', 'skipped', 'failed');
    }

    /**
     * The effective pattern for a run: the client's, or the definition's
     * default when none is sent. Throws when it does not compile or misses
     * the named groups the mapping depends on.
     */
    public function normalizedPattern(DocumentBundleImporter $definition, ?string $pattern): string
    {
        $pattern = $pattern !== null && trim($pattern) !== '' ? trim($pattern) : $definition->defaultPattern();

        if (! DocumentBundlePattern::isValid($pattern)) {
            throw new InvalidArgumentException(__('import.document_invalid_pattern'));
        }

        return $pattern;
    }

    /**
     * @param  list<UploadedFile>  $files
     * @return list<array<string, mixed>>
     */
    private function resolvedRows(DocumentBundleImporter $definition, array $files, ?string $pattern): array
    {
        $pattern = $this->normalizedPattern($definition, $pattern);
        $claimed = [];
        $rows = [];

        foreach ($files as $file) {
            $name = (string) $file->getClientOriginalName();
            $parts = $this->parseParts($pattern, $name);

            if ($parts === null) {
                $rows[] = $this->rejectedRow($file, 'pattern', __('import.document_pattern_mismatch'));

                continue;
            }

            $resolution = $definition->resolve($parts);

            if ($resolution->isRejected()) {
                $rows[] = $this->rejectedRow(
                    $file,
                    (string) $resolution->reasonCode,
                    (string) $resolution->reason,
                    $resolution->details,
                );

                continue;
            }

            /** @var DocumentImportTarget $target */
            $target = $resolution->target;
            $identity = $definition->dedupeKey($target);

            if (isset($claimed[$identity])) {
                $rows[] = $this->rejectedRow($file, 'duplicate', __('import.document_batch_duplicate'));

                continue;
            }

            if ($definition->targetExists($target)) {
                $rows[] = $this->rejectedRow($file, 'duplicate', __('import.document_duplicate'));

                continue;
            }

            $claimed[$identity] = true;
            $rows[] = [
                'file' => $name,
                'file_object' => $file,
                'status' => 'ready',
                'reason_code' => null,
                'reason' => null,
                'target' => $target,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, string>  $details
     * @return array<string, mixed>
     */
    private function rejectedRow(UploadedFile $file, string $reasonCode, string $reason, array $details = []): array
    {
        return [
            'file' => (string) $file->getClientOriginalName(),
            'file_object' => $file,
            'status' => $reasonCode,
            'reason_code' => $reasonCode,
            'reason' => $reason,
            'details' => $details,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function planRow(array $row): array
    {
        $target = $row['target'] ?? null;
        $details = $row['details'] ?? ($target instanceof DocumentImportTarget ? $target->details : []);

        return [
            'file' => $row['file'],
            'status' => $row['status'],
            'number' => $details['number'] ?? null,
            'date' => $details['date'] ?? null,
            'meeting' => $details['meeting'] ?? null,
            'reason_code' => $row['reason_code'],
            'reason' => $row['reason'],
        ];
    }

    /**
     * Parse a filename against the pattern. Returns the named groups the
     * mapping understands, or null when the filename does not match.
     *
     * @return array<string, string>|null
     */
    private function parseParts(string $pattern, string $filename): ?array
    {
        if (preg_match($pattern, $filename, $matches) !== 1) {
            return null;
        }

        $parts = [];

        foreach (['number', 'ordinal', 'date'] as $key) {
            if (isset($matches[$key]) && $matches[$key] !== '') {
                $parts[$key] = (string) $matches[$key];
            }
        }

        return $parts;
    }
}
