<?php

namespace App\Domains\BoardMeeting\Controllers;

use App\Domains\BoardMeeting\Events\FileTemplateDeleted;
use App\Domains\BoardMeeting\Events\FileTemplateUpdated;
use App\Domains\BoardMeeting\Events\FileTemplateUploaded;
use App\Domains\BoardMeeting\Exports\BoardMeetingProfile;
use App\Domains\BoardMeeting\Exports\BoardMeetingSection;
use App\Domains\BoardMeeting\Exports\BoardMeetingSheetExport;
use App\Domains\BoardMeeting\Exports\BoardMeetingTemplateFields;
use App\Domains\BoardMeeting\Models\BoardMeeting;
use App\Domains\BoardMeeting\Requests\ReplaceFileTemplateRequest;
use App\Domains\BoardMeeting\Requests\StoreFileTemplateRequest;
use App\Models\User;
use App\Support\Exports\Exceptions\InvalidFileTemplate;
use App\Support\Exports\Templates\FileTemplate;
use App\Support\Exports\Templates\FileTemplateLibrary;
use App\Support\Exports\Templates\FileTemplateService;
use App\Support\Exports\Templates\FileTemplateStore;
use App\Support\Exports\Templates\GeneralValues;
use App\Support\Exports\Templates\RangeMacro;
use App\Support\Exports\Templates\TemplateField;
use App\Support\Exports\Templates\TemplateFillerRegistry;
use App\Support\Exports\Templates\TemplateKind;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The library of template files a meeting can be exported with.
 *
 * Two read-only views, a preview, and four writes. The LIST is for the export
 * picker, the FIELDS are the cheat sheet that tells a person what a template may
 * contain, and both are needed together: a picker without the cheat sheet asks
 * somebody to guess names, and a cheat sheet without the picker is a document
 * nobody can apply. The PREVIEW is the loop between them: it fills the stored
 * template with a real meeting so the author sees the layout instead of guessing
 * from the syntax.
 *
 * Each template is reported with the one fact that decides how it behaves — does
 * it have placeholders — because a caller showing "content" or "style" beside the
 * name is answering a question the picker will otherwise be asked again at export
 * time.
 */
final class FileTemplateController
{
    public function __construct(
        private readonly FileTemplateLibrary $library,
        private readonly FileTemplateService $files,
        private readonly FileTemplateStore $store,
    ) {}

    /**
     * Every template for a domain, default first.
     */
    public function index(Request $request): JsonResponse
    {
        $domain = $this->domain($request);
        $templates = $this->library->forFormatDomain($this->format($request), $domain, $this->entity($request));

        $changes = $this->library->lastChanges($templates);
        $actors = $this->actorNames($changes);

        return response()->json([
            'message' => __('board_meeting.template.library'),
            'data' => [
                'domain' => $domain,
                // The format the caller asked about, so a Word picker never
                // offers a Markdown frame it could not export from.
                'format' => $this->format($request),
                'templates' => array_map(
                    fn (FileTemplate $template): array => $this->present(
                        $template,
                        $changes[$template->key()] ?? null,
                        $actors,
                    ),
                    $templates,
                ),
            ],
        ]);
    }

    /**
     * The names a template may refer to, grouped so repeating rows are visible.
     *
     * The grouping is the whole point of this endpoint. A flat list of 28 names
     * teaches the wrong thing: it cannot say which of them have to share a table
     * row, and that is the single mistake that makes a list render as one line
     * where the author expected twelve.
     */
    public function fields(): JsonResponse
    {
        $registry = new BoardMeetingTemplateFields;

        return response()->json([
            'message' => __('board_meeting.template.fields'),
            'data' => [
                'domain' => $registry->domain(),
                'syntax' => [
                    'open' => '{{',
                    'close' => '}}',
                    // The one name the server never lists as a field because it
                    // is not a value: it is the document itself. Shipped rather
                    // than left for the client to spell, so the marker a template
                    // is recognised by has one definition.
                    'marker' => TemplateKind::CONTENT,
                ],
                // Listed with the domain's own fields rather than as an afterthought,
                // because a placeholder nobody is shown is a placeholder nobody uses —
                // and these four are the ones a GENERAL template needs in order to
                // work on a domain it was not written for.
                'general' => array_map(
                    fn (string $key): array => ['key' => $key, 'label' => __('board_meeting.template.general.'.$key)],
                    GeneralValues::names(),
                ),
                'inline' => array_values(array_map(
                    fn ($field): array => ['key' => $field->key, 'label' => $field->label],
                    array_filter($registry->fields(), fn ($field): bool => ! $field->isRow()),
                )),
                'rows' => $this->rowGroups($registry),

                // THE BULLET LIST, reported as its own list because it is a
                // shape nothing else in this payload describes: a row repeats a
                // TABLE ROW, and a range repeats a BLOCK of paragraphs, which is
                // the only way to get one item per paragraph outside a table.
                // Without this an author would have to guess the marker, and a
                // marker nobody can guess is not a feature.
                'blocks' => $this->blockRanges($registry),

                // The names that accept a separator of the author's own. Nearly
                // every repeating name is a row and takes its shape from the
                // table; these four are prose that happens to be a list.
                'listable' => $registry->listableKeys(),

                // The names that hold a date, and so also answer to
                // `{{name@jalali}}`. REPORTED rather than generated: the cheat sheet
                // is read before any record exists, so there is no value to inspect
                // and only the domain knows which of its names are dates.
                'dates' => array_values(array_map(
                    static fn (TemplateField $field): string => $field->key,
                    array_filter($registry->fields(), static fn (TemplateField $field): bool => $field->isDate),
                )),

                // Which section each value appears in, which is the question a person
                // laying out a template is actually asking. It had no answer while the
                // sections and the values were two separate lists, and a flat list of
                // twenty-two keys does not say that ttendee.role lives under the
                // attendees heading.
                'sections' => array_map(
                    static fn (BoardMeetingSection $section): array => [
                        'key' => $section->key,
                        'label' => $section->label,
                        'fields' => $section->fieldKeys(),
                    ],
                    (new BoardMeetingProfile)->sections(),
                ),
            ],
        ]);
    }

    /**
     * The stored template filled with the newest meeting, as a file to open.
     *
     * THE AUTHORING GUIDE CAN CHECK SYNTAX; ONLY THIS CAN SHOW THE LAYOUT. A typo
     * is caught before upload, but "does the attendee table fit the page" is a
     * question about the produced document, and until now the only way to ask it
     * was to run a real export.
     *
     * IT USES THE NEWEST MEETING because a preview needs some record and a meeting
     * picker is a second feature; the newest is the one an author is most likely
     * to recognise. Nothing is stored, and the bytes are in the template's own
     * format.
     *
     * TEMPLATE MANAGEMENT ALONE IS NOT ENOUGH. The file contains a real meeting,
     * so the route also asks for `board-meeting.export`; the same permission that
     * lets somebody take a copy of a meeting away.
     */
    public function preview(Request $request, string $slug): StreamedResponse
    {
        $domain = $this->domain($request);

        // This controller is still the board-meeting one (F6); another domain's
        // template cannot be filled with a board meeting, so it is not a template
        // this endpoint can preview.
        if ($domain !== BoardMeetingSheetExport::TEMPLATE_DOMAIN) {
            abort(404, __('board_meeting.template.unknown_domain', ['domains' => $domain]));
        }

        $template = $this->library->find($this->format($request), $domain, $slug);

        if ($template === null) {
            abort(404, __('board_meeting.template.not_found'));
        }

        $meeting = BoardMeeting::query()
            ->orderByDesc('held_on')
            ->orderByDesc('id')
            ->first();

        if ($meeting === null) {
            abort(422, __('board_meeting.template.preview_no_meeting'));
        }

        $file = $this->files->render(
            app(BoardMeetingSheetExport::class),
            $meeting,
            app(BoardMeetingTemplateFields::class),
            $template,
            null,
            $template->format(),
        );

        return response()->streamDownload(function () use ($file): void {
            $stream = fopen('php://output', 'w');
            $file->copyTo($stream);
        }, 'preview-'.$template->slug().'.'.pathinfo($file->filename, PATHINFO_EXTENSION), [
            'Content-Type' => $file->mimeType,
            'Cache-Control' => 'no-store',
        ]);
    }

    public function store(StoreFileTemplateRequest $request): JsonResponse
    {
        try {
            $template = $this->library->upload(
                file: $request->file('file'),
                format: $request->templateFormat(),
                domain: $request->domain(),
                name: $request->name(),
                entity: null,
                isDefault: $request->isDefault(),
                uploadedBy: $this->actor($request),
            );
        } catch (InvalidFileTemplate $rejected) {
            // 422 rather than 500: the file was refused for something about
            // itself, which is the same class of answer as any other validation
            // failure and is shown to the person who uploaded it.
            return response()->json([
                'message' => $rejected->getMessage(),
                'errors' => ['file' => [$rejected->getMessage()]],
            ], 422);
        }

        event(new FileTemplateUploaded($template));

        return response()->json([
            'message' => __('board_meeting.template.saved'),
            'data' => $this->present($template, ['at' => now(), 'by' => $this->actor($request)]),
            // What the file turned out to be, at the moment the person who
            // uploaded it can still do something about it. Whether the generated
            // meeting is written into a template is read off the file rather than
            // chosen, so the answer arrives as a result instead of an intention —
            // and the first place it would otherwise show up is an export somebody
            // has to open to find out they got the other kind.
            //
            // The file is inspected a second time rather than carried out of
            // `upload()`, which would mean returning two values from a method
            // thirty call sites already use. The work is repeated; the LOGIC is
            // not, which is the half that would drift.
            'inspection' => $this->library->inspect(
                $request->file('file'),
                $request->templateFormat(),
            ),
        ], 201);
    }

    public function update(Request $request, string $slug): JsonResponse
    {
        $domain = $this->domain($request);
        $template = $this->library->find($this->format($request), $domain, $slug);

        if ($template === null) {
            abort(404, __('board_meeting.template.not_found'));
        }

        $before = $template;

        if ($request->filled('name')) {
            $template = $this->library->rename($template, (string) $request->input('name'), $this->actor($request));
        }

        if ($request->boolean('is_default')) {
            $template = $this->library->promoteDefault($template, $this->actor($request));
        }

        // One event for the one PATCH, and none for a request that changed
        // nothing: an audit row saying "updated" with no diff is noise that makes
        // the rows which matter harder to find.
        if ($before->name() !== $template->name() || $before->isDefault() !== $template->isDefault()) {
            event(new FileTemplateUpdated($before, $template));
        }

        return response()->json([
            'message' => __('board_meeting.template.saved'),
            'data' => $this->present($template, ['at' => now(), 'by' => $this->actor($request)]),
        ]);
    }

    /**
     * Replace the file behind an existing template.
     *
     * Same slug, same name, same format, same default flag: only the bytes and
     * the size/mime that describe them change. A POST rather than the PUT the
     * review suggested, because PHP does not populate `$_FILES` for a PUT
     * multipart request; this is the operation, not the verb.
     */
    public function replace(ReplaceFileTemplateRequest $request, string $slug): JsonResponse
    {
        $domain = $this->domain($request);
        $template = $this->library->find($this->format($request), $domain, $slug);

        if ($template === null) {
            abort(404, __('board_meeting.template.not_found'));
        }

        try {
            $replacement = $this->library->replace(
                $template,
                $request->file('file'),
                $this->actor($request),
            );
        } catch (InvalidFileTemplate $rejected) {
            return response()->json([
                'message' => $rejected->getMessage(),
                'errors' => ['file' => [$rejected->getMessage()]],
            ], 422);
        }

        event(new FileTemplateUploaded($replacement, replaced: true));

        return response()->json([
            'message' => __('board_meeting.template.replaced'),
            'data' => $this->present($replacement, ['at' => now(), 'by' => $this->actor($request)]),
            // The same rule the upload response follows: the kind is read off
            // the FILE, and the person who replaced it is the only one who can
            // still act on it — the export is the first place a Frame turned
            // Detail would otherwise show up.
            'inspection' => $this->library->inspect(
                $request->file('file'),
                $replacement->format(),
            ),
        ]);
    }

    /**
     * The stored template file, raw.
     *
     * The first half of the replace loop: a person downloads the current file,
     * edits it, and posts it back through the replacement endpoint. The bytes
     * are the STORED ones — no meeting is filled into them — so this asks for
     * template management alone, not the meeting-export permission the preview
     * needs.
     */
    public function download(Request $request, string $slug): StreamedResponse
    {
        $template = $this->library->find($this->format($request), $this->domain($request), $slug);

        if ($template === null) {
            abort(404, __('board_meeting.template.not_found'));
        }

        $stream = $this->store->readStream($template);

        if ($stream === null) {
            // The row outlived its file. A named 404 rather than a download of
            // nothing, the same honesty the export path applies.
            abort(404, __('board_meeting.template.file_missing'));
        }

        return response()->streamDownload(function () use ($stream): void {
            stream_copy_to_stream($stream, fopen('php://output', 'w'));
            fclose($stream);
        }, $template->slug().'.'.$template->format(), [
            'Content-Type' => $this->rawMime($template->format()),
            'Cache-Control' => 'no-store',
        ]);
    }

    public function destroy(Request $request, string $slug): JsonResponse
    {
        $domain = $this->domain($request);
        $template = $this->library->find($this->format($request), $domain, $slug);

        if ($template === null) {
            abort(404, __('board_meeting.template.not_found'));
        }

        $this->library->delete($template);

        event(new FileTemplateDeleted($template));

        return response()->json([
            'message' => __('board_meeting.template.deleted'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(FileTemplate $template, ?array $change = null, array $actors = []): array
    {
        return [
            'slug' => $template->slug(),
            'name' => $template->name(),
            // The format is part of what the row IS, not metadata about the file:
            // a .md template and a .docx template are different templates that
            // happen to be listed together, and a library that cannot say which is
            // which is a list of names with extensions hidden.
            'format' => $template->format(),
            'size' => $template->size(),
            'is_default' => $template->isDefault(),
            // Who last wrote this row and when — the question a letterhead's
            // list is for. Write responses pass the change that just happened
            // (the actor's own request, so the name resolves on the next list
            // read); the list reads the stamp from the store.
            'updated_at' => ($change['at'] ?? null)?->toISOString(),
            'updated_by' => $change['by'] ?? null,
            'updated_by_name' => isset($change['by']) ? ($actors[$change['by']] ?? null) : null,
        ];
    }

    /**
     * The readable name behind every actor id in the changes, in one query.
     *
     * @param  array<string, array{at: Carbon|null, by: int|null}>  $changes
     * @return array<int, string>
     */
    private function actorNames(array $changes): array
    {
        $ids = array_values(array_filter(array_column($changes, 'by')));

        if ($ids === []) {
            return [];
        }

        return User::query()->whereIn('id', $ids)->pluck('name', 'id')->all();
    }

    /**
     * Row fields as the rows they belong to.
     *
     * @return list<array{group: string, columns: list<array{key: string, label: string}>}>
     */
    private function rowGroups(BoardMeetingTemplateFields $registry): array
    {
        // Which label each field's section calls it, so a GROUP can be shown with the
        // same Persian name the document uses rather than a second translation of it
        // kept here. A group and a section are genuinely the same thing seen from two
        // ends — one is how the values repeat, the other is where they appear on the
        // page — so borrowing the label is not a shortcut, it is the same answer.
        $labels = [];

        foreach ((new BoardMeetingProfile)->sections() as $section) {
            foreach ($section->fieldKeys() as $key) {
                $labels[$key] ??= $section->label;
            }
        }

        $groups = [];

        foreach ($registry->fields() as $field) {
            if (! $field->isRow()) {
                continue;
            }

            $groups[$field->group]['group'] = $field->group;
            $groups[$field->group]['label'] ??= $labels[$field->key] ?? $field->group;
            $groups[$field->group]['columns'][] = [
                'key' => $field->key,
                'label' => $field->label,
            ];
        }

        return array_values($groups);
    }

    /**
     * The groups that may be written as a repeated BLOCK rather than a repeated row.
     *
     * Every row group can, and the markers are PHPWord's own `cloneBlock` shape — an
     * author who has written a Word template has already met `{{name}} … {{/name}}`,
     * so this is a spelling they half know rather than one they must be taught.
     *
     * @return list<array{group: string, open: string, close: string}>
     */
    private function blockRanges(BoardMeetingTemplateFields $registry): array
    {
        return array_map(
            static fn (array $group): array => [
                'group' => $group['group'],
                'open' => RangeMacro::open($group['group']),
                'close' => RangeMacro::close($group['group']),
            ],
            $this->rowGroups($registry),
        );
    }

    private function domain(Request $request): string
    {
        return (string) $request->input('domain', BoardMeetingSheetExport::TEMPLATE_DOMAIN);
    }

    /**
     * What the raw download claims to be, by the format it is filed under.
     *
     * The stored mime is what the uploader's browser guessed off the bytes, and
     * for a Word file that guess is usually application/zip — the container, not
     * the document. The format is the key the template lives under, so it
     * decides.
     */
    private function rawMime(string $format): string
    {
        return match ($format) {
            FileTemplate::FORMAT_DOCX => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'md' => 'text/markdown',
            'json' => 'application/json',
            default => 'application/octet-stream',
        };
    }

    /**
     * The format whose templates this request is about.
     *
     * Read from the request so the library can grow without this controller
     * gaining a branch per format, but constrained to a registered renderer:
     * an unknown format would otherwise write a key no renderer can serve, and
     * the template would sit in the library offering itself to nobody.
     */
    /**
     * The format a READ is about, from the query string.
     *
     * Deliberately different from the upload path, which derives the format from the
     * file and refuses one it cannot place. Here nothing is being stored, so an
     * unknown format falls back to Word rather than refusing a listing — and it is
     * validated against the FILLER registry rather than the renderer one, because a
     * format nobody can fill is a format whose templates could never be exported.
     */
    private function format(Request $request): string
    {
        $format = (string) $request->input('format', FileTemplate::FORMAT_DOCX);

        return app(TemplateFillerRegistry::class)->has($format)
            ? $format
            : FileTemplate::FORMAT_DOCX;
    }

    private function entity(Request $request): ?string
    {
        $entity = $request->input('entity');

        return is_string($entity) && $entity !== '' ? $entity : null;
    }

    private function actor(Request $request): ?int
    {
        $user = $request->user();

        return $user === null ? null : (int) $user->getAuthIdentifier();
    }
}
