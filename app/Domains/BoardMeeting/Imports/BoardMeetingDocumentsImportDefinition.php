<?php

namespace App\Domains\BoardMeeting\Imports;

use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Support\BoardMeetingApprovalCategory;
use App\Domains\Document\Models\Document;
use App\Domains\Document\Services\DocumentService;
use App\Support\Imports\Contract\DocumentBundleImporter;
use App\Support\Imports\Value\DocumentImportResolution;
use App\Support\Imports\Value\DocumentImportTarget;
use Illuminate\Http\UploadedFile;

/**
 * The Archive filename contract, productized (vault idea 567, order B step
 * 2 — «import bulk مصوب to meetings based on the file name»): a scanned
 * مصوبه's filename carries its registry number and Jalali date; the date
 * resolves the meeting (`held_on_jalali`, the key the archive files
 * meetings under — ADR-011), and the number becomes the usage's
 * `registry_number` with the archive convention (`{ordinal}/{number}/ص/{year}`
 * or `{number}/ص/{year}`), matching the retired migrate-tck stage 6. The
 * default pattern is a suggestion the admin may edit per run; the meaning
 * of the named groups number / ordinal / date is the contract itself.
 */
final class BoardMeetingDocumentsImportDefinition implements DocumentBundleImporter
{
    /**
     * The archive shape: 1001(1401-10-12).pdf or 1001-2(1401-10-12).pdf —
     * registry number (+ optional ordinal) and the Jalali date in
     * parentheses.
     */
    public const DEFAULT_PATTERN = '/^(?<number>\d{4})(?:-(?<ordinal>\d+))?\((?<date>\d{4}-\d{2}-\d{2})\)\.pdf$/';

    public function __construct(
        private readonly DocumentService $documents,
        private readonly BoardMeetingApprovalCategory $category,
    ) {}

    public function name(): string
    {
        return 'board-meeting-documents';
    }

    public function label(): string
    {
        return 'اسناد مصوبات جلسات (ورود گروهی از آرشیو)';
    }

    public function defaultPattern(): string
    {
        return self::DEFAULT_PATTERN;
    }

    public function maxFiles(): int
    {
        return (int) config('documents.board-meeting.max_files', 50);
    }

    public function resolve(array $parts): DocumentImportResolution
    {
        $number = $parts['number'] ?? null;
        $date = $parts['date'] ?? null;

        if ($number === null || $date === null) {
            return DocumentImportResolution::rejected(
                'pattern',
                __('import.document_pattern_mismatch'),
            );
        }

        $meeting = BoardMeeting::query()->where('held_on_jalali', $date)->first();

        if ($meeting === null) {
            return DocumentImportResolution::rejected(
                'no_meeting',
                __('import.document_no_meeting', ['date' => $date]),
                ['number' => $number, 'date' => $date],
            );
        }

        $ordinal = $parts['ordinal'] ?? null;
        $year = substr($date, 0, 4);
        $registryNumber = $ordinal !== null
            ? "{$ordinal}/{$number}/ص/{$year}"
            : "{$number}/ص/{$year}";

        return DocumentImportResolution::accepted(new DocumentImportTarget(
            entity: $meeting,
            metadata: ['registry_number' => $registryNumber],
            details: [
                'number' => $registryNumber,
                'date' => $date,
                'meeting' => ['id' => $meeting->id, 'label' => $meeting->registryLabel()],
            ],
        ));
    }

    public function dedupeKey(DocumentImportTarget $target): string
    {
        return $target->entity::class
            .':'.$target->entity->getKey()
            .':'.$target->metadata['registry_number'];
    }

    public function targetExists(DocumentImportTarget $target): bool
    {
        return $target->entity->documentUsages()
            ->where('metadata->registry_number', $target->metadata['registry_number'])
            ->exists();
    }

    public function persist(UploadedFile $file, DocumentImportTarget $target): Document
    {
        return $this->documents->upload(
            $target->entity,
            $file,
            $this->category->category(),
            metadata: $target->metadata,
        );
    }

    public function serveRouteName(): string
    {
        return 'board-meeting.documents.serve';
    }
}
