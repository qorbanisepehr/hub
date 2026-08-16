<?php

namespace App\Domains\Authorization\Controllers;

use App\Domains\Authorization\Attributes\AttributeRegistry;
use App\Domains\Authorization\Attributes\AuthorizationAttribute;
use App\Domains\Authorization\Policies\Operator;
use App\Domains\Authorization\Policies\PolicyValidator;
use Illuminate\Http\JsonResponse;

/**
 * Exposes the policy vocabulary (attribute resource types, operators, value
 * sources) so the rule builder UI can render forms without hardcoding the
 * backend DSL.
 */
class RuleBuilderMetaController
{
    /** @var array<string, string> */
    private const OPERATOR_LABELS = [
        'equals' => 'برابر با',
        'not_equals' => 'نامساوی',
        'in' => 'در مجموعه',
        'not_in' => 'خارج از مجموعه',
        'contains' => 'شامل',
        'not_contains' => 'شامل نباشد',
        'starts_with' => 'شروع با',
        'ends_with' => 'پایان با',
        'greater_than' => 'بزرگ‌تر از',
        'greater_than_or_equal' => 'بزرگ‌تر یا مساوی',
        'less_than' => 'کوچک‌تر از',
        'less_than_or_equal' => 'کوچک‌تر یا مساوی',
        'is_null' => 'خالی است',
        'is_not_null' => 'خالی نیست',
        'exists' => 'موجود است',
        'not_exists' => 'موجود نیست',
    ];

    /** @var array<string, string> */
    private const VALUE_SOURCE_LABELS = [
        'literal' => 'مقدار ثابت',
        'actor' => 'ویژگی کاربر',
        'resource' => 'ویژگی منبع',
        'context' => 'محتوا (Context)',
    ];

    public function __invoke(
        AttributeRegistry $attributes,
        PolicyValidator $validator,
    ): JsonResponse {
        $resourceTypes = [];

        foreach ($attributes->resourceTypes() as $resourceType) {
            $resourceTypes[] = [
                'key' => $resourceType,
                'label' => $attributes->resourceTypeLabel($resourceType) ?? $resourceType,
                'attributes' => array_values(array_map(
                    fn (AuthorizationAttribute $attribute) => [
                        'key' => $attribute->key,
                        'label' => $attribute->label,
                        'type' => $attribute->type,
                        'queryable' => $attribute->queryable,
                        'operators' => $validator->operatorsForType($attribute->type),
                    ],
                    $attributes->all($resourceType),
                )),
            ];
        }

        $operators = [];

        foreach (Operator::cases() as $operator) {
            $operators[] = [
                'key' => $operator->value,
                'label' => self::OPERATOR_LABELS[$operator->value] ?? $operator->value,
            ];
        }

        $valueSources = [];

        foreach (array_keys(self::VALUE_SOURCE_LABELS) as $source) {
            $valueSources[] = [
                'key' => $source,
                'label' => self::VALUE_SOURCE_LABELS[$source],
            ];
        }

        return response()->json([
            'data' => [
                'resource_types' => $resourceTypes,
                'operators' => $operators,
                'value_sources' => $valueSources,
            ],
        ]);
    }
}
