<?php

namespace App\Domains\BoardMeeting\Controllers;

use App\Domains\BoardMeeting\Exports\BoardMeetingProfile;
use App\Domains\BoardMeeting\Exports\BoardMeetingSection;
use App\Domains\BoardMeeting\Exports\BoardMeetingSheetExport;
use App\Domains\BoardMeeting\Exports\BoardMeetingTemplateFields;
use App\Domains\BoardMeeting\Requests\StoreFileTemplateRequest;
use App\Support\Exports\Exceptions\InvalidFileTemplate;
use App\Support\Exports\Templates\FileTemplate;
use App\Support\Exports\Templates\FileTemplateLibrary;
use App\Support\Exports\Templates\GeneralValues;
use App\Support\Exports\Templates\TemplateFillerRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The library of template files a meeting can be exported with.
 *
 * Two read-only views and three writes. The LIST is for the export picker, the
 * FIELDS are the cheat sheet that tells a person what a template may contain,
 * and both are needed together: a picker without the cheat sheet asks somebody
 * to guess names, and a cheat sheet without the picker is a document nobody can
 * apply.
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
    ) {}

    /**
     * Every template for a domain, default first.
     */
    public function index(Request $request): JsonResponse
    {
        $domain = $this->domain($request);

        return response()->json([
            'message' => __('board_meeting.template.library'),
            'data' => [
                'domain' => $domain,
                // The format the caller asked about, so a Word picker never
                // offers a Markdown frame it could not export from.
                'format' => $this->format($request),
                'templates' => array_map(
                    fn (FileTemplate $template): array => $this->present($template),
                    $this->library->forFormatDomain($this->format($request), $domain, $this->entity($request)),
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
                'syntax' => ['open' => '{{', 'close' => '}}'],
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

        return response()->json([
            'message' => __('board_meeting.template.saved'),
            'data' => $this->present($template),
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

        if ($request->filled('name')) {
            $template = $this->library->rename($template, (string) $request->input('name'));
        }

        if ($request->boolean('is_default')) {
            $template = $this->library->promoteDefault($template);
        }

        return response()->json([
            'message' => __('board_meeting.template.saved'),
            'data' => $this->present($template),
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

        return response()->json([
            'message' => __('board_meeting.template.deleted'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(FileTemplate $template): array
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
        ];
    }

    /**
     * Row fields as the rows they belong to.
     *
     * @return list<array{group: string, columns: list<array{key: string, label: string}>}>
     */
    private function rowGroups(BoardMeetingTemplateFields $registry): array
    {
        $groups = [];

        foreach ($registry->fields() as $field) {
            if ($field->isRow()) {
                $groups[$field->group]['group'] = $field->group;
                $groups[$field->group]['columns'][] = [
                    'key' => $field->key,
                    'label' => $field->label,
                ];
            }
        }

        return array_values($groups);
    }

    private function domain(Request $request): string
    {
        return (string) $request->input('domain', BoardMeetingSheetExport::TEMPLATE_DOMAIN);
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
