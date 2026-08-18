<?php

namespace App\Domains\Authorization\Policies;

use App\Domains\Authorization\Attributes\AttributeRegistry;
use App\Domains\Authorization\Models\Permission;

/**
 * Structural and vocabulary validation for a policy condition tree. Used before
 * an access rule is persisted and defensively during evaluation. Policies are a
 * bounded DSL — no arbitrary PHP or SQL can pass validation.
 */
final class PolicyValidator
{
    /** @var array<string, list<string>> */
    private const OPERATORS_BY_TYPE = [
        'integer' => ['equals', 'not_equals', 'in', 'not_in', 'greater_than', 'greater_than_or_equal', 'less_than', 'less_than_or_equal', 'is_null', 'is_not_null', 'exists', 'not_exists'],
        'date' => ['equals', 'not_equals', 'in', 'not_in', 'greater_than', 'greater_than_or_equal', 'less_than', 'less_than_or_equal', 'is_null', 'is_not_null', 'exists', 'not_exists'],
        'string' => ['equals', 'not_equals', 'in', 'not_in', 'contains', 'not_contains', 'starts_with', 'ends_with', 'is_null', 'is_not_null', 'exists', 'not_exists'],
        'boolean' => ['equals', 'not_equals', 'is_null', 'is_not_null', 'exists', 'not_exists'],
    ];

    public const VALUE_SOURCES = ['literal', 'actor', 'resource', 'context'];

    public function __construct(private readonly AttributeRegistry $attributes) {}

    /**
     * Validate a condition tree for a resource type. Returns a list of
     * human-readable errors; an empty list means valid.
     *
     * @return list<string>
     */
    public function errors(mixed $node, string $resourceType): array
    {
        return $this->validateNode($node, $resourceType, '$');
    }

    /**
     * Validate a condition tree against the resource type the permission
     * resolves to. Permissions without a registered attribute resource type
     * cannot carry a policy.
     *
     * @return list<string>
     */
    public function errorsForPermission(mixed $node, int $permissionId): array
    {
        $permission = Permission::query()->where('id', $permissionId)->where('is_active', true)->first();

        if ($permission === null) {
            return ['Permission not found.'];
        }

        $resourceType = $permission->policyResourceType();

        if ($resourceType === null || ! $this->attributes->resourceTypeRegistered($resourceType)) {
            return ['این مجوز قابلیت تعریف قانون (Condition) ندارد.'];
        }

        return $this->errors($node, $resourceType);
    }

    /** Operators allowed for an attribute type. */
    public function operatorsForType(string $type): array
    {
        return self::OPERATORS_BY_TYPE[$type] ?? [];
    }

    public function assertValid(mixed $node, string $resourceType): void
    {
        $errors = $this->errors($node, $resourceType);

        if ($errors !== []) {
            throw new \InvalidArgumentException('Invalid authorization policy: '.implode('; ', $errors));
        }
    }

    /** @return list<string> */
    private function validateNode(mixed $node, string $resourceType, string $path): array
    {
        if (! is_array($node)) {
            return ["{$path}: policy node must be an object."];
        }

        if (array_key_exists('all', $node)) {
            return $this->validateGroup($node['all'], $resourceType, $path.'.all');
        }

        if (array_key_exists('any', $node)) {
            return $this->validateGroup($node['any'], $resourceType, $path.'.any');
        }

        if (array_key_exists('not', $node)) {
            return $this->validateNode($node['not'], $resourceType, $path.'.not');
        }

        return $this->validateLeaf($node, $resourceType, $path);
    }

    private function validateGroup(mixed $children, string $resourceType, string $path): array
    {
        if (! is_array($children) || array_is_list($children) === false) {
            return ["{$path}: group must be a list of conditions."];
        }

        $errors = [];

        foreach ($children as $index => $child) {
            $errors = [...$errors, ...$this->validateNode($child, $resourceType, "{$path}[{$index}]")];
        }

        return $errors;
    }

    /** @param  array<string, mixed>  $leaf */
    private function validateLeaf(array $leaf, string $resourceType, string $path): array
    {
        $errors = [];

        $attribute = (string) ($leaf['attribute'] ?? '');
        $operatorName = (string) ($leaf['operator'] ?? '');
        $valueSource = (string) ($leaf['value_source'] ?? 'literal');

        if ($attribute === '') {
            $errors[] = "{$path}.attribute: required.";
        } elseif (! $this->attributes->has($resourceType, $attribute)) {
            $errors[] = "{$path}.attribute: unknown attribute [{$attribute}] for resource type [{$resourceType}].";
        }

        $operator = Operator::tryFromName($operatorName);

        if ($operator === null) {
            $errors[] = "{$path}.operator: unknown operator [{$operatorName}].";
        } elseif ($attribute !== '' && $this->attributes->has($resourceType, $attribute)) {
            $definition = $this->attributes->definition($resourceType, $attribute);
            $allowed = self::OPERATORS_BY_TYPE[$definition->type] ?? [];

            if (! in_array($operatorName, $allowed, true)) {
                $errors[] = "{$path}.operator: operator [{$operatorName}] is not supported for attribute [{$attribute}].";
            }
        }

        if (! in_array($valueSource, self::VALUE_SOURCES, true)) {
            $errors[] = "{$path}.value_source: unknown value source [{$valueSource}].";
        }

        if (in_array($operatorName, ['is_null', 'is_not_null', 'exists', 'not_exists'], true) && $valueSource !== 'literal') {
            $errors[] = "{$path}.value_source: null/exists operators must use the literal value source.";
        }

        return $errors;
    }
}
