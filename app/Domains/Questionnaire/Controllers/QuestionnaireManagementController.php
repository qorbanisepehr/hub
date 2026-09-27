<?php

namespace App\Domains\Questionnaire\Controllers;

use App\Contracts\Authorization;
use App\Domains\FormOptions\Services\FormOptionService;
use App\Domains\Questionnaire\Exports\QuestionnaireDocument;
use App\Domains\Questionnaire\Models\Questionnaire;
use App\Domains\Questionnaire\Resources\QuestionnaireResource;
use App\Domains\Questionnaire\Services\QuestionnaireService;
use App\Support\Exports\DocumentExportService;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuestionnaireManagementController extends Controller
{
    private const SORTABLE = [
        'created_at',
        'updated_at',
        'first_name',
        'last_name',
    ];

    private const SEARCHABLE = [
        'first_name',
        'last_name',
        'email',
        'mobile',
    ];

    public function __construct(
        private Authorization $authorization,
        private QuestionnaireService $questionnaireService,
        private DocumentExportService $documents,
        private FormOptionService $formOptions,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        // The management list only surfaces submitted questionnaires; a
        // per-status filter would fight this base constraint.
        $query = Questionnaire::query()->where('status', 'submitted');

        $this->authorization->scope($request->user(), 'questionnaire.view', $query);

        ListQuery::search($query, ListQuery::filter($request), self::SEARCHABLE);

        $query->orderBy(
            ListQuery::sort($request, self::SORTABLE, 'created_at'),
            ListQuery::order($request),
        );

        $questionnaires = $query->paginate(ListQuery::perPage($request));

        return QuestionnaireResource::collection($questionnaires);
    }

    public function show(Request $request, string $questionnaire): JsonResponse
    {
        $model = Questionnaire::where('uuid', $questionnaire)
            ->orWhere('id', $questionnaire)
            ->firstOrFail();

        $this->authorization->authorize($request->user(), 'questionnaire.view', $model);

        return response()->json([
            'data' => new QuestionnaireResource($model),
        ]);
    }

    /**
     * The printable questionnaire document (PDF/Word) for one record. Gated
     * by the same questionnaire.view permission as show().
     */
    public function document(Request $request, string $questionnaire): StreamedResponse|JsonResponse
    {
        $model = Questionnaire::where('uuid', $questionnaire)
            ->orWhere('id', $questionnaire)
            ->firstOrFail();

        $this->authorization->authorize($request->user(), 'questionnaire.view', $model);

        $format = $request->query('format', 'pdf');

        if (! in_array($format, ['pdf', 'docx'], true)) {
            return response()->json(['message' => __('authorization.format_not_supported')], 422);
        }

        $file = $this->documents->run(
            new QuestionnaireDocument($this->questionnaireService, $this->formOptions),
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
