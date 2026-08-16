<?php

namespace App\Domains\Authorization\Controllers;

use App\Contracts\Authorization;
use App\Domains\Authorization\Attributes\AttributeRegistry;
use App\Domains\Authorization\Models\Permission;
use App\Domains\Authorization\Policies\ConditionEvaluator;
use App\Domains\Authorization\Policies\PolicyValidator;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Rule preview for the rule builder: evaluate a single candidate access rule
 * (permission + optional policy) against a target user and resource, alongside
 * the user's current effective decision for the same permission.
 */
class RulePreviewController
{
    public function __invoke(
        Request $request,
        Authorization $authorization,
        AttributeRegistry $attributes,
        ConditionEvaluator $evaluator,
        PolicyValidator $validator,
    ): JsonResponse {
        $data = $request->validate([
            'permission' => ['required', 'string'],
            'policy' => ['nullable', 'array'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'resource_type' => ['nullable', 'string'],
            'resource_id' => ['nullable', 'integer'],
        ]);

        $permission = Permission::query()
            ->where('name', $data['permission'])
            ->where('is_active', true)
            ->firstOrFail();

        if (isset($data['policy']) && $data['policy'] !== null) {
            $errors = $validator->errorsForPermission($data['policy'], $permission->id);

            if ($errors !== []) {
                return response()->json(['message' => implode(' ', $errors)], 422);
            }
        }

        $targetUser = User::query()->findOrFail($data['user_id']);

        $resource = null;

        if (($data['resource_type'] ?? null) !== null && ($data['resource_id'] ?? null) !== null) {
            $model = $attributes->modelForResourceType($data['resource_type']);

            abort_unless(is_string($model), 422, 'Invalid resource type.');

            $resource = $model::query()->findOrFail($data['resource_id']);
        }

        $policy = $data['policy'] ?? null;
        $ruleMatches = $policy === null
            ? true
            : $evaluator->evaluates($policy, $targetUser, $resource, null);

        return response()->json([
            'data' => [
                'rule_matches' => $ruleMatches,
                'effective' => $authorization->explain($targetUser, $permission->name, $resource)->toArray(),
            ],
        ]);
    }
}
