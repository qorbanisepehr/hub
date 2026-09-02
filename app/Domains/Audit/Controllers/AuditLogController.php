<?php

namespace App\Domains\Audit\Controllers;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Audit\Resources\AuditLogDetailResource;
use App\Domains\Audit\Resources\AuditLogResource;
use App\Domains\Audit\Services\AuditQueryService;
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
        'event', 'category', 'actor_type', 'actor_id', 'actor_role_id',
        'subject_type', 'subject_id', 'date_from', 'date_to',
        'request_id', 'trace_id', 'ip', 'search',
    ];

    private const EXPORT_COLUMNS = [
        'id', 'event', 'category', 'actor_type', 'actor_id',
        'subject_type', 'subject_id', 'description',
        'ip_address', 'request_id', 'trace_id', 'created_at',
    ];

    public function __construct(
        private AuditQueryService $queryService,
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
     * Stream the filtered audit log as a CSV or JSONL download (v6 §64–65).
     * Chunked server-side so even full-table exports never blow memory.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->validateDateFilters($request);

        $validated = $request->validate([
            'format' => ['nullable', 'string', Rule::in(['csv', 'jsonl'])],
        ]);

        $format = $validated['format'] ?? 'csv';
        $filters = $request->only(self::FILTERS);

        return response()->streamDownload(function () use ($filters, $format): void {
            $logs = $this->queryService->stream($filters);

            if ($format === 'jsonl') {
                foreach ($logs as $log) {
                    echo json_encode($log->attributesToArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    echo PHP_EOL;
                }

                return;
            }

            $handle = fopen('php://output', 'w');
            fputcsv($handle, self::EXPORT_COLUMNS, ',', '"', '\\');

            foreach ($logs as $log) {
                fputcsv($handle, array_map(
                    fn (string $column): string => (string) $log->{$column},
                    self::EXPORT_COLUMNS,
                ), ',', '"', '\\');
            }

            fclose($handle);
        }, 'audit-logs-'.now()->format('Ymd-His').".{$format}", [
            'Content-Type' => $format === 'jsonl'
                ? 'application/x-ndjson'
                : 'text/csv; charset=UTF-8',
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
