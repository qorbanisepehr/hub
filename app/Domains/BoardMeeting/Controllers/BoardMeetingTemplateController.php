<?php

namespace App\Domains\BoardMeeting\Controllers;

use App\Domains\BoardMeeting\Exports\BoardMeetingSheetExport;
use App\Domains\BoardMeeting\Requests\BoardMeetingTemplateRequest;
use App\Domains\Settings\Repositories\SystemSettingsRepositoryInterface;
use App\Support\Exports\Templates\ExportTemplate;
use App\Support\Exports\Templates\TemplateResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Which sections a printed meeting contains.
 *
 * Two levels, and they are the same two the resolver walks: the domain's
 * template, and an override for one meeting. Writing one does not touch the
 * other, and removing one is its own endpoint rather than an empty body — a
 * route that means "set" and a body that means "clear" is two ways to say one
 * thing, and the pair has to be kept in step forever.
 *
 * Every response carries the stored template AND the sections that will
 * actually render. A caller showing only the stored value would tell an
 * administrator their template is `['summary']` on a meeting where the domain
 * default quietly supplies the other seven, and the two numbers would never sit
 * side by side for anyone to notice the difference.
 */
final class BoardMeetingTemplateController
{
    public function __construct(
        private readonly BoardMeetingSheetExport $sheet,
        private readonly TemplateResolver $resolver,
        private readonly SystemSettingsRepositoryInterface $settings,
    ) {}

    /**
     * The domain-level template and every per-meeting override stored.
     */
    public function index(): JsonResponse
    {
        $available = $this->sheet->sectionDefinitions();

        return response()->json([
            'message' => __('board_meeting.template.index'),
            'data' => [
                'available' => $available,
                'stored' => $this->resolver->stored($this->domain())?->sections(),
                'effective' => $this->resolver->sections(
                    $this->domain(),
                    null,
                    array_column($available, 'key'),
                ),
                'overrides' => array_values(array_map(
                    fn (ExportTemplate $template): array => [
                        'entity' => $template->entity(),
                        'sections' => $template->sections(),
                    ],
                    // The domain's own template is listed by `stored`, not here;
                    // it has no entity and would otherwise appear as an override
                    // of something.
                    array_filter(
                        $this->resolver->forDomain($this->domain()),
                        fn (ExportTemplate $template): bool => $template->entity() !== null,
                    ),
                )),
            ],
        ]);
    }

    /**
     * The sheet's vocabulary and the template in force for one meeting.
     */
    public function show(int $meeting): JsonResponse
    {
        $entity = $this->entityKey($meeting);
        $available = $this->sheet->sectionDefinitions();

        return response()->json([
            'message' => __('board_meeting.template.read'),
            'data' => [
                'available' => $available,
                'entity' => $entity,
                'stored' => $this->resolver->stored($this->domain(), $entity)?->sections(),
                'effective' => $this->resolver->sections(
                    $this->domain(),
                    $entity,
                    array_column($available, 'key'),
                ),
            ],
        ]);
    }

    /**
     * Store the domain-level template.
     */
    public function update(BoardMeetingTemplateRequest $request): JsonResponse
    {
        $sections = $request->sections();

        $this->settings->put(
            ExportTemplate::make($this->domain(), null, $sections),
            $this->updatedBy($request),
        );

        return response()->json([
            'message' => __('board_meeting.template.saved'),
            'data' => ['sections' => $sections],
        ]);
    }

    /**
     * Store an override for one meeting.
     */
    public function updateForMeeting(
        int $meeting,
        BoardMeetingTemplateRequest $request,
    ): JsonResponse {
        $entity = $this->entityKey($meeting);
        $sections = $request->sections();

        $this->settings->put(
            ExportTemplate::make($this->domain(), $entity, $sections),
            $this->updatedBy($request),
        );

        return response()->json([
            'message' => __('board_meeting.template.saved'),
            'data' => ['entity' => $entity, 'sections' => $sections],
        ]);
    }

    /**
     * Drop one meeting's override, so it falls back to the domain's template.
     */
    public function destroyForMeeting(int $meeting): JsonResponse
    {
        $entity = $this->entityKey($meeting);

        $this->resolver->forget($this->domain(), $entity);

        return response()->json([
            'message' => __('board_meeting.template.cleared'),
            'data' => ['entity' => $entity, 'sections' => null],
        ]);
    }

    private function domain(): string
    {
        return BoardMeetingSheetExport::TEMPLATE_DOMAIN;
    }

    /**
     * How one meeting is addressed inside the templates group.
     *
     * `session/{id}` rather than the bare id: an id is only meaningful under a
     * domain's namespace, and a flat `42` in this group would claim meeting 42
     * and CV 42 at once. It matches the key the export resolves against, so the
     * two cannot disagree about where a meeting's override lives.
     */
    private function entityKey(int $meeting): string
    {
        return 'session/'.$meeting;
    }

    /**
     * From the request rather than injected `auth()`: the form request is
     * already the current request, and one fewer parameter is one fewer thing
     * that can resolve to nothing at dispatch time.
     */
    private function updatedBy(Request $request): ?int
    {
        $user = $request->user();

        return $user === null ? null : (int) $user->getAuthIdentifier();
    }
}
