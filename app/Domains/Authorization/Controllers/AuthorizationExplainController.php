<?php

namespace App\Domains\Authorization\Controllers;

use App\Contracts\Authorization;
use App\Domains\Authorization\Attributes\AttributeRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthorizationExplainController
{
    public function __invoke(Request $request, Authorization $authorization, AttributeRegistry $attributes): JsonResponse
    {
        $data = $request->validate([
            'permission' => ['required', 'string'],
            'resource_type' => ['nullable', 'string'],
            'resource_id' => ['nullable', 'integer'],
        ]);

        $resource = null;

        if (($data['resource_type'] ?? null) !== null && ($data['resource_id'] ?? null) !== null) {
            $model = $attributes->modelForResourceType($data['resource_type']);

            abort_unless(is_string($model), 422, 'Invalid resource type.');

            /** @var class-string<Model> $model */
            $resource = $model::query()->findOrFail($data['resource_id']);
        }

        $decision = $authorization->explain($request->user(), $data['permission'], $resource);

        return response()->json($decision->toArray());
    }
}
