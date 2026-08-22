<?php

namespace App\Domains\Audit\Controllers;

use App\Domains\Audit\Events\RetentionPolicyCreated;
use App\Domains\Audit\Events\RetentionPolicyDeleted;
use App\Domains\Audit\Events\RetentionPolicyUpdated;
use App\Domains\Audit\Models\AuditRetentionPolicy;
use App\Domains\Audit\Resources\AuditRetentionPolicyResource;
use App\Domains\Audit\Services\AuditEventDispatcher;
use App\Domains\Audit\Services\PolicyResolver;
use App\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditRetentionController extends ApiController
{
    protected ?string $model = null;

    public function __construct(
        private PolicyResolver $policyResolver,
        private readonly AuditEventDispatcher $audit,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $policies = AuditRetentionPolicy::orderByDesc('is_active')
            ->orderBy('category')
            ->orderBy('event')
            ->paginate(20);

        return AuditRetentionPolicyResource::collection($policies);
    }

    public function store(Request $request): AuditRetentionPolicyResource
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'event' => 'nullable|string|max:255',
            'retention_days' => 'required|integer|min:1',
            'archive_after_days' => 'nullable|integer|min:1',
            'archive_enabled' => 'boolean',
            'delete_after_archive' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $policy = AuditRetentionPolicy::create($validated);
        $this->policyResolver->flushCache();

        $this->audit->record(new RetentionPolicyCreated($policy));

        return new AuditRetentionPolicyResource($policy);
    }

    public function show(AuditRetentionPolicy $auditRetentionPolicy): AuditRetentionPolicyResource
    {
        return new AuditRetentionPolicyResource($auditRetentionPolicy);
    }

    public function update(Request $request, AuditRetentionPolicy $auditRetentionPolicy): AuditRetentionPolicyResource
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'event' => 'nullable|string|max:255',
            'retention_days' => 'required|integer|min:1',
            'archive_after_days' => 'nullable|integer|min:1',
            'archive_enabled' => 'boolean',
            'delete_after_archive' => 'boolean',
            'is_active' => 'boolean',
        ]);

        $old = $auditRetentionPolicy->only(array_keys($validated));
        $auditRetentionPolicy->update($validated);
        $new = $auditRetentionPolicy->only(array_keys($validated));
        $this->policyResolver->flushCache();

        $this->audit->record(new RetentionPolicyUpdated($auditRetentionPolicy, $old, $new));

        return new AuditRetentionPolicyResource($auditRetentionPolicy);
    }

    public function destroy(AuditRetentionPolicy $auditRetentionPolicy): JsonResponse
    {
        $this->audit->record(new RetentionPolicyDeleted($auditRetentionPolicy));

        $auditRetentionPolicy->delete();
        $this->policyResolver->flushCache();

        return response()->json(['message' => 'Retention policy deleted']);
    }
}
