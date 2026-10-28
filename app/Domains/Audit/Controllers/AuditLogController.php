<?php

namespace App\Domains\Audit\Controllers;

use App\Domains\Audit\Exports\AuditLogExporter;
use App\Domains\Audit\Models\AuditLog;
use App\Domains\Audit\Resources\AuditLogDetailResource;
use App\Domains\Audit\Resources\AuditLogResource;
use App\Domains\Audit\Services\AuditQueryService;
use App\Support\Exports\ExportService;
use App\Support\Exports\Value\ExportRequest;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController
{
    /** @var list<string> Filters shared by the index, stats, and export endpoints. */
    private const FILTERS = [
        'event', 'event_not', 'category', 'category_not',
        'actor_type', 'actor_id', 'actor_role_id',
        'subject_type', 'subject_id', 'date_from', 'date_to',
        'request_id', 'trace_id', 'ip', 'filter',
    ];

    public function __construct(
        private AuditQueryService $queryService,
        private ExportService $exports,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->validateDateFilters($request);

        $filters = $request->only(self::FILTERS);

        $perPage = ListQuery::perPage($request, max: 100);

        // Presence of the param opts into keyset pagination; `?cursor=` alone
        // fetches the first cursor page.
        $cursor = $request->has('cursor') ? (string) $request->input('cursor', '') : null;

        $logs = $this->queryService->paginate(
            $filters,
            $perPage,
            $cursor,
            ListQuery::sort($request),
            ListQuery::order($request),
        );

        return AuditLogResource::collection($logs);
    }

    public function show(AuditLog $auditLog): AuditLogDetailResource
    {
        $auditLog->load(['actorUser:id,name,avatar_url', 'actorUser.employee:id,user_id,first_name,last_name']);

        return new AuditLogDetailResource($auditLog);
    }

    public function stats(Request $request): JsonResponse
    {
        $filters = $request->only(['event', 'category', 'actor_type', 'actor_id', 'actor_role_id', 'subject_type', 'subject_id', 'date_from', 'date_to']);

        return response()->json([
            'data' => $this->queryService->stats($filters),
        ]);
    }

    /**
     * Stream the filtered audit log as a CSV, JSONL, PDF or Word download.
     * Chunked server-side so even full-table exports never blow memory;
     * format bytes come from the shared export kernel (Support\Exports).
     * PDF/Word route through the kernel's document bridge and are capped at
     * config('exports.sync_row_limit') rows (422 above it).
     */
    public function export(Request $request): StreamedResponse
    {
        $this->validateDateFilters($request);

        $validated = $request->validate([
            'format' => ['nullable', 'string', Rule::in(['csv', 'jsonl', 'pdf', 'docx'])],
        ]);

        $exportRequest = new ExportRequest(
            format: $validated['format'] ?? 'csv',
            context: $request->only(self::FILTERS),
        );

        $exporter = new AuditLogExporter($this->queryService);
        $file = $this->exports->run($exporter, $exportRequest);

        return response()->streamDownload(function () use ($file): void {
            $stream = fopen('php://output', 'w');
            $file->copyTo($stream);
        }, $file->filename, [
            'Content-Type' => $file->mimeType,
        ]);
    }

    /**
     * List distinct events, optionally filtered by category.
     */
    public function events(Request $request): JsonResponse
    {
        $events = $this->queryService->distinctEvents(
            category: $request->input('category'),
        );

        return response()->json(['data' => $events]);
    }

    /**
     * Validate that date_from and date_to are parseable dates.
     *
     * @throws ValidationException
     */
    private function validateDateFilters(Request $request): void
    {
        foreach (['date_from', 'date_to'] as $param) {
            $value = $request->input($param);

            if ($value === null) {
                continue;
            }

            try {
                Carbon::parse($value);
            } catch (\InvalidArgumentException) {
                throw ValidationException::withMessages([
                    $param => "The {$param} must be a valid date.",
                ]);
            }
        }
    }
}
