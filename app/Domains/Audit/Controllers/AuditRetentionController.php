<?php

namespace App\Domains\Audit\Controllers;

use App\Domains\Audit\Models\AuditRetentionPolicy;
use App\Domains\Audit\Resources\AuditRetentionPolicyResource;
use App\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditRetentionController extends ApiController
{
    protected ?string $model = AuditRetentionPolicy::class;

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

        $auditRetentionPolicy->update($validated);

        return new AuditRetentionPolicyResource($auditRetentionPolicy);
    }

    public function destroy(AuditRetentionPolicy $auditRetentionPolicy): JsonResponse
    {
        $auditRetentionPolicy->delete();

        return response()->json(['message' => 'Retention policy deleted']);
    }
}
