<?php

namespace App\Domains\Authorization\Policies;

use App\Domains\Authorization\Attributes\AttributeRegistry;
use App\Domains\Authorization\Engine\AuthorizationContext;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Evaluates a policy condition tree against a concrete actor, resource, and
 * context. A node is either a leaf condition or a group:
 *
 *   {"all": [leaf, group, ...]}   every child must match
 *   {"any": [leaf, ...]}          at least one child must match
 *   {"not": node}                 child must NOT match
 *   {"attribute","operator","value_source","value"}   leaf
 *
 * Value sources: literal, actor, resource, context.
 */
final class ConditionEvaluator
{
    public function __construct(private readonly AttributeRegistry $attributes) {}

    public function evaluates(
        mixed $node,
        User $actor,
        mixed $resource,
        ?AuthorizationContext $context,
    ): bool {
        if (! is_array($node)) {
            return false;
        }

        if (array_key_exists('all', $node)) {
            return $this->allMatch($node['all'], $actor, $resource, $context);
        }

        if (array_key_exists('any', $node)) {
            return $this->anyMatch($node['any'], $actor, $resource, $context);
        }

        if (array_key_exists('not', $node)) {
            return ! $this->evaluates($node['not'], $actor, $resource, $context);
        }

        return $this->leaf($node, $actor, $resource, $context);
    }

    /** @param  array<int, mixed>  $children */
    private function allMatch(array $children, User $actor, mixed $resource, ?AuthorizationContext $context): bool
    {
        foreach ($children as $child) {
            if (! $this->evaluates($child, $actor, $resource, $context)) {
                return false;
            }
        }

        return true;
    }

    /** @param  array<int, mixed>  $children */
    private function anyMatch(array $children, User $actor, mixed $resource, ?AuthorizationContext $context): bool
    {
        foreach ($children as $child) {
            if ($this->evaluates($child, $actor, $resource, $context)) {
                return true;
            }
        }

        return false;
    }

    /** @param  array<string, mixed>  $leaf */
    private function leaf(array $leaf, User $actor, mixed $resource, ?AuthorizationContext $context): bool
    {
        $operator = Operator::tryFromName((string) ($leaf['operator'] ?? ''));

        if ($operator === null) {
            return false;
        }

        $left = $this->resolveAttribute((string) ($leaf['attribute'] ?? ''), $resource);
        $right = $this->resolveValue(
            (string) ($leaf['value_source'] ?? 'literal'),
            $leaf['value'] ?? null,
            $actor,
            $resource,
            $context,
        );

        return $operator->applies($left, $right);
    }

    private function resolveAttribute(string $key, mixed $resource): mixed
    {
        if (! $resource instanceof Model) {
            return null;
        }

        $resourceType = $this->attributes->resourceTypeFor($resource);

        if ($resourceType === null || ! $this->attributes->has($resourceType, $key)) {
            return null;
        }

        return $this->attributes->resolve($resource, $key);
    }

    private function resolveValue(
        string $source,
        mixed $value,
        User $actor,
        mixed $resource,
        ?AuthorizationContext $context,
    ): mixed {
        return match ($source) {
            'literal' => $value,
            'actor' => $this->attributes->resolve($actor, 'user.'.ltrim((string) $value, '.')),
            'resource' => $resource instanceof Model
                ? $this->attributes->resolve($resource, (string) $value)
                : null,
            'context' => $context?->get((string) $value),
            default => null,
        };
    }
}
