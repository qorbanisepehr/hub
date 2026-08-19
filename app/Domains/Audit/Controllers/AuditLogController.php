<?php

namespace App\Domains\Audit\Controllers;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Audit\Resources\AuditLogDetailResource;
use App\Domains\Audit\Resources\AuditLogResource;
use App\Domains\Audit\Services\AuditQueryService;
use App\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditLogController extends ApiController
{
    protected ?string $model = AuditLog::class;

    public function __construct(
        private AuditQueryService $queryService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->only([
            'event', 'category', 'actor_type', 'actor_id', 'actor_role_id',
            'subject_type', 'subject_id', 'date_from', 'date_to',
            'request_id', 'search',
        ]);

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        $logs = $this->queryService->paginate($filters, $perPage);

        return AuditLogResource::collection($logs);
    }

    public function show(AuditLog $auditLog): AuditLogDetailResource
    {
        return new AuditLogDetailResource($auditLog);
    }

    public function stats(Request $request): JsonResponse
    {
        $filters = $request->only([
            'event', 'category', 'actor_type', 'actor_id', 'actor_role_id',
            'subject_type', 'subject_id', 'date_from', 'date_to',
        ]);

        return response()->json([
            'data' => $this->queryService->stats($filters),
        ]);
    }
}
