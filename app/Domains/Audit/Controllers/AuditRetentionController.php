<?php

namespace App\Domains\Audit\Controllers;

use App\Domains\Audit\Events\RetentionPolicyCreated;
use App\Domains\Audit\Events\RetentionPolicyDeleted;
use App\Domains\Audit\Events\RetentionPolicyUpdated;
use App\Domains\Audit\Models\AuditRetentionPolicy;
use App\Domains\Audit\Requests\StoreRetentionPolicyRequest;
use App\Domains\Audit\Requests\UpdateRetentionPolicyRequest;
use App\Domains\Audit\Resources\AuditRetentionPolicyResource;
use App\Domains\Audit\Services\PolicyResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditRetentionController
{
    public function __construct(
        private PolicyResolver $policyResolver,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $policies = AuditRetentionPolicy::orderByDesc('is_active')
            ->orderBy('category')
            ->orderBy('event')
            ->paginate(20);

        return AuditRetentionPolicyResource::collection($policies);
    }

    public function store(StoreRetentionPolicyRequest $request): AuditRetentionPolicyResource
    {
        $validated = $request->validated();

        $policy = AuditRetentionPolicy::create($validated);
        $this->policyResolver->flushCache();

        event(new RetentionPolicyCreated($policy));

        return new AuditRetentionPolicyResource($policy);
    }

    public function show(AuditRetentionPolicy $auditRetentionPolicy): AuditRetentionPolicyResource
    {
        return new AuditRetentionPolicyResource($auditRetentionPolicy);
    }

    public function update(UpdateRetentionPolicyRequest $request, AuditRetentionPolicy $auditRetentionPolicy): AuditRetentionPolicyResource
    {
        $validated = $request->validated();

        $old = $auditRetentionPolicy->only(array_keys($validated));
        $auditRetentionPolicy->update($validated);
        $new = $auditRetentionPolicy->only(array_keys($validated));
        $this->policyResolver->flushCache();

        event(new RetentionPolicyUpdated($auditRetentionPolicy, $old, $new));

        return new AuditRetentionPolicyResource($auditRetentionPolicy);
    }

    public function destroy(AuditRetentionPolicy $auditRetentionPolicy): JsonResponse
    {
        event(new RetentionPolicyDeleted($auditRetentionPolicy));

        $auditRetentionPolicy->delete();
        $this->policyResolver->flushCache();

        return response()->json(['message' => 'Retention policy deleted']);
    }
}
