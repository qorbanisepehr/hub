<?php

namespace App\Domains\BoardMeeting\Services;

use App\Domains\BoardMeeting\Exports\BoardMeetingSheetExport;
use App\Domains\BoardMeeting\Exports\BoardMeetingTemplateFields;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentUsage;
use App\Domains\Document\Services\DocumentService;
use App\Support\Exports\BundleEntry;
use App\Support\Exports\BundleManifest;
use App\Support\Exports\BundleRequest;
use App\Support\Exports\DocumentExportService;
use App\Support\Exports\Templates\WordTemplate;
use App\Support\Exports\Templates\WordTemplateService;
use App\Support\Exports\Value\Document\DocumentSpec;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * What one bundle of meetings is made of, and the pages that say so.
 *
 * Exists because "the files for these meetings" was about to be written twice —
 * once for a single meeting's archive and once for a bulk one — and the two
 * copies would have drifted the way they always do, until the single-meeting
 * archive quietly stopped matching the bulk one for the same meeting.
 *
 * Authorization is NOT here, and deliberately: this turns meetings that have
 * already been allowed into files, and says nothing about who may ask. The
 * caller must have cleared every usage's download check before calling, which
 * is the same rule the Document domain's own bundle follows.
 *
 * One folder per meeting, always — including when a bundle holds exactly one.
 * That is the document bundle's own convention (a folder per owner), and
 * consistency is worth more here than saving a level of nesting: the manifest
 * is the flat listing, so a reader who wants to know what is inside does not
 * have to guess which folder a file landed in.
 */
final class BoardMeetingBundle
{
    public function __construct(
        private readonly DocumentExportService $exports,
        private readonly DocumentService $documentService,
    ) {}

    /**
     * The archive's entries, in the order they should be added.
     *
     * @param  list<BoardMeeting>  $meetings
     * @param  WordTemplate|null  $wordTemplate  applied to the `docx` sheets, and
     *                                           only when one is chosen
     * @return list<BundleEntry>
     *
     * @throws Throwable when a sheet cannot be rendered; anything already
     *                   spilled to disk for this bundle is removed first
     */
    public function entries(array $meetings, BundleRequest $request, ?WordTemplate $wordTemplate = null): array
    {
        $entries = [];

        try {
            $sheet = app(BoardMeetingSheetExport::class);
            $word = app(WordTemplateService::class);
            $fields = app(BoardMeetingTemplateFields::class);

            // One meeting stays flat; two or more get a folder each. A folder
            // named after the only meeting inside it teaches a reader nothing,
            // and the document bundle groups by owner because it can hold many
            // entities — this one holds exactly one.
            $oneMeeting = count($meetings) === 1;

            foreach ($meetings as $meeting) {
                $folder = $oneMeeting ? null : $this->folderFor($meeting);

                foreach ($request->formats as $format) {
                    // A template only speaks about the format it can speak
                    // about. Handing it to the PDF path would do nothing, and
                    // letting a pdf-only request silently acquire a docx-only
                    // meaning would be a coincidence nobody could rely on.
                    $file = $format === 'docx'
                        ? $word->render($sheet, $meeting, $fields, $wordTemplate)
                        : $this->exports->run($sheet, $meeting, $format);

                    $entries[] = BundleEntry::fromExport(
                        $file,
                        $this->sheetName($meeting, $format),
                        $folder,
                    );
                }

                if (! $request->wantsAttachments()) {
                    continue;
                }

                foreach ($this->attachmentsOf($meeting) as [$entryName, $path]) {
                    $entries[] = BundleEntry::fromFile($path, $entryName, $folder);
                }
            }
        } catch (Throwable $error) {
            // A sheet already rendered spills a temporary file to disk. Left
            // behind when a later meeting fails, those would accumulate in the
            // temp directory for as long as the machine is up, so the bundle
            // cleans up after itself rather than trusting the builder — which
            // never saw these entries.
            $this->discardTemporaries($entries);

            throw $error;
        }

        return $entries;
    }

    /**
     * The manifest: what is in the archive and which meeting each file is for.
     *
     * Derived from the entries rather than described separately, so a file
     * cannot be listed under a meeting it is not in, or missed because
     * somebody forgot to add it to a second list.
     *
     * @param  list<BoardMeeting>  $meetings
     * @param  list<BundleEntry>  $entries
     */
    public function manifest(array $meetings, array $entries, bool $withAttachments): DocumentSpec
    {
        $byFolder = [];

        foreach ($entries as $entry) {
            $byFolder[$entry->folder ?? ''][] = $entry->entryName;
        }

        $items = array_map(
            fn (BoardMeeting $meeting): array => [
                'label' => (string) $meeting->registryLabel(),
                'files' => $byFolder[$this->folderFor($meeting)] ?? [],
            ],
            $meetings,
        );

        return BundleManifest::forItems(
            title: (string) __('board_meeting.print.bundle_manifest'),
            itemsLabel: (string) __('board_meeting.print.bundle_items'),
            filesLabel: (string) __('board_meeting.print.bundle_files'),
            items: $items,
        );
    }

    /**
     * The manifest as two entries: a page for a person, and the same page as
     * data for a machine. One spec rendered twice, so the two cannot disagree.
     *
     * @param  list<BundleEntry>  $entries
     * @return list<BundleEntry>
     */
    public function manifestEntries(DocumentSpec $spec): array
    {
        return [
            BundleEntry::fromExport($this->exports->render($spec, 'md'), 'manifest.md'),
            BundleEntry::fromExport($this->exports->render($spec, 'json'), 'manifest.json'),
        ];
    }

    /**
     * ASCII-safe folder name, from the meeting's own registry key.
     */
    private function folderFor(BoardMeeting $meeting): string
    {
        $label = (string) $meeting->registryLabel();

        // Persian registry labels become empty under a plain slug, so the
        // sequence is the fallback rather than an unreadable folder.
        $slug = Str::slug($label);

        return $slug !== '' ? $slug : 'meeting-'.$meeting->getKey();
    }

    private function sheetName(BoardMeeting $meeting, string $format): string
    {
        $stem = $meeting->sequence !== null && $meeting->sequence > 0
            ? 'meeting-'.$meeting->sequence
            : 'meeting-'.$meeting->getKey();

        return "{$stem}-summary.{$format}";
    }

    /**
     * @return list<array{0: string, 1: string}> [entryName, absolutePath]
     */
    private function attachmentsOf(BoardMeeting $meeting): array
    {
        $attachments = [];

        foreach ($meeting->documentUsages as $usage) {
            if ($usage->trashed()) {
                continue;
            }

            $document = $usage->document;

            if (! $document instanceof Document) {
                continue;
            }

            // Absolute, because the zip reads it with ZipArchive rather than through a
            // disk. The relative storage path would only resolve from the
            // storage root, which is not where the file is.
            $attachments[] = [
                $this->attachmentName($document, $usage),
                Storage::disk($document->disk)->path($document->path),
            ];
        }

        return $attachments;
    }

    /**
     * ASCII name from the same placement slug the document bundle uses, so one
     * scan reads identically whichever archive it arrived in.
     */
    private function attachmentName(Document $document, DocumentUsage $usage): string
    {
        $slug = $this->documentService->structureNames($document, $usage)['slug'];

        $extension = pathinfo($document->path, PATHINFO_EXTENSION);

        return $extension !== '' ? "{$slug}.{$extension}" : $slug;
    }

    /**
     * @param  list<BundleEntry>  $entries
     */
    private function discardTemporaries(array $entries): void
    {
        foreach ($entries as $entry) {
            if ($entry->temporary) {
                @unlink($entry->sourcePath);
            }
        }
    }
}
