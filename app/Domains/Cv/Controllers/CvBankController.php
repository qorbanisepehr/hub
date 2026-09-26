<?php

namespace App\Domains\Cv\Controllers;

use App\Contracts\Authorization;
use App\Domains\Cv\Exports\CvDocument;
use App\Domains\Cv\Models\Cv;
use App\Domains\Cv\Resources\CvResource;
use App\Domains\Cv\Services\CvService;
use App\Domains\FormOptions\Services\FormOptionService;
use App\Domains\Questionnaire\Resources\QuestionnaireResource;
use App\Support\Exports\DocumentExportService;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CvBankController extends Controller
{
    private const SORTABLE = [
        'created_at',
        'updated_at',
        'version',
        'first_name',
        'last_name',
        'full_name',
        'mobile',
        'email',
        'status',
    ];

    private const SEARCHABLE = [
        'first_name',
        'last_name',
        'email',
        'mobile',
    ];

    public function __construct(
        private Authorization $authorization,
        private CvService $cvService,
        private DocumentExportService $documents,
        private FormOptionService $formOptions,
    ) {}

    /**
     * The UI sorts the combined name column under one id; the database sorts
     * by the leading first name.
     */
    private function sortColumn(string $id): string
    {
        return $id === 'full_name' ? 'first_name' : $id;
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Cv::query()
            ->with('documentUsages.document')
            ->with('reviewer.employee')
            ->with('reviewer.activeRole');

        $this->authorization->scope($request->user(), 'cv.view', $query);

        ListQuery::search($query, ListQuery::filter($request), self::SEARCHABLE);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('status_not')) {
            $query->where('status', '!=', $request->input('status_not'));
        }

        $query->orderBy(
            $this->sortColumn(ListQuery::sort($request, self::SORTABLE, 'created_at')),
            ListQuery::order($request),
        );

        $paginator = $query->paginate(ListQuery::perPage($request));
        CvResource::preloadLifecycleUsers($request, $paginator->items());

        return CvResource::collection($paginator);
    }

    public function show(Request $request, string $cv): JsonResponse
    {
        // A UUID literal can't be compared against the uuid column by Postgres
        // when the route receives the numeric id, so branch on the value type.
        $model = Str::isUuid($cv)
            ? Cv::with('questionnaire')->with('documentUsages.document')->with('reviewer.employee')->with('reviewer.activeRole')->where('uuid', $cv)->firstOrFail()
            : Cv::with('questionnaire')->with('documentUsages.document')->with('reviewer.employee')->with('reviewer.activeRole')->where('id', $cv)->firstOrFail();

        $this->authorization->authorize($request->user(), 'cv.view', $model);
        CvResource::preloadLifecycleUsers($request, [$model]);

        return response()->json([
            'data' => new CvResource($model),
        ]);
    }

    public function createQuestionnaire(Request $request, string $cv): JsonResponse
    {
        $model = Cv::where('uuid', $cv)->firstOrFail();

        $this->authorization->authorize($request->user(), 'cv.create-questionnaire', $model);

        $questionnaire = $this->cvService->createQuestionnaireFromCv(
            $model,
            $request->user()?->getKey(),
        );

        return response()->json([
            'data' => new QuestionnaireResource($questionnaire),
            'message' => __('cv.questionnaire_created'),
        ], 201);
    }

    /**
     * The printable CV document (PDF/Word) for one bank record. Gated by the
     * same cv.view permission as show(); accepts the uuid or the numeric id
     * exactly like show() (implicit binding would only resolve the id).
     */
    public function document(Request $request, string $cv): StreamedResponse|JsonResponse
    {
        $model = Str::isUuid($cv)
            ? Cv::where('uuid', $cv)->firstOrFail()
            : Cv::where('id', $cv)->firstOrFail();

        $this->authorization->authorize($request->user(), 'cv.view', $model);

        $format = $request->query('format', 'pdf');

        if (! in_array($format, ['pdf', 'docx'], true)) {
            return response()->json(['message' => __('authorization.format_not_supported')], 422);
        }

        $file = $this->documents->run(
            new CvDocument($this->cvService, $this->formOptions),
            $model,
            $format,
        );

        return response()->streamDownload(function () use ($file): void {
            $stream = fopen('php://output', 'w');
            $file->copyTo($stream);
        }, $this->documents->dispositionFilename($file), [
            'Content-Type' => $file->mimeType,
            'Cache-Control' => 'no-store',
        ]);
    }
}
