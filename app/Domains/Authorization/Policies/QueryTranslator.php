<?php

namespace App\Domains\Authorization\Policies;

use App\Domains\Authorization\Attributes\AttributeRegistry;
use App\Domains\Authorization\Engine\AuthorizationContext;
use App\Domains\Authorization\Exceptions\AuthorizationScopeException;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Translates a policy condition tree into Eloquent query constraints for
 * collection scoping. Only queryable attributes with scalar (literal/actor/
 * context) values can be translated; anything else raises an
 * AuthorizationScopeException — scope() must never fall back to loading and
 * filtering in PHP.
 */
final class QueryTranslator
{
    public function __construct(private readonly AttributeRegistry $attributes) {}

    public function apply(
        Builder $query,
        User $actor,
        string $resourceType,
        mixed $node,
        ?AuthorizationContext $context,
    ): Builder {
        return $this->whereCondition($query, $node, 'where', $actor, $resourceType, $context);
    }

    private function whereCondition(
        Builder $query,
        mixed $node,
        string $boolean,
        User $actor,
        string $resourceType,
        ?AuthorizationContext $context,
    ): Builder {
        if (! is_array($node)) {
            throw new \InvalidArgumentException('Invalid authorization policy node.');
        }

        if (array_key_exists('all', $node)) {
            return $this->group($query, $node['all'], $boolean, 'where', $actor, $resourceType, $context);
        }

        if (array_key_exists('any', $node)) {
            return $this->group($query, $node['any'], $boolean, 'orWhere', $actor, $resourceType, $context);
        }

        if (array_key_exists('not', $node)) {
            return $query->{$boolean.'Not'}(function (Builder $inner) use ($node, $actor, $resourceType, $context): void {
                $this->whereCondition($inner, $node['not'], 'where', $actor, $resourceType, $context);
            });
        }

        return $this->leaf($query, $node, $boolean, $actor, $resourceType, $context);
    }

    /**
     * @param  array<int, mixed>  $children
     */
    private function group(
        Builder $query,
        array $children,
        string $boolean,
        string $childBoolean,
        User $actor,
        string $resourceType,
        ?AuthorizationContext $context,
    ): Builder {
        return $query->{$boolean}(function (Builder $inner) use ($children, $childBoolean, $actor, $resourceType, $context): void {
            foreach ($children as $child) {
                $this->whereCondition($inner, $child, $childBoolean, $actor, $resourceType, $context);
            }
        });
    }

    /** @param  array<string, mixed>  $leaf */
    private function leaf(
        Builder $query,
        array $leaf,
        string $boolean,
        User $actor,
        string $resourceType,
        ?AuthorizationContext $context,
    ): Builder {
        $attribute = (string) ($leaf['attribute'] ?? '');
        $operatorName = (string) ($leaf['operator'] ?? '');

        $definition = $this->attributes->definition($resourceType, $attribute);

        if ($definition === null || ! $definition->queryable || $definition->column === null) {
            throw AuthorizationScopeException::forAttribute($attribute);
        }

        $value = $this->resolveValue((string) ($leaf['value_source'] ?? 'literal'), $leaf['value'] ?? null, $actor, $context);

        $column = $definition->column;
        $operator = Operator::tryFromName($operatorName);

        if ($operator === null) {
            throw new \InvalidArgumentException("Unknown authorization operator [{$operatorName}].");
        }

        return match ($operator) {
            Operator::Equals => $query->{$boolean}($column, '=', $value),
            Operator::NotEquals => $query->{$boolean}($column, '!=', $value),
            Operator::In => is_array($value) ? $query->{$boolean.'In'}($column, $value) : $query,
            Operator::NotIn => is_array($value) ? $query->{$boolean.'NotIn'}($column, $value) : $query,
            Operator::Contains => $query->{$boolean}($column, 'like', '%'.$value.'%'),
            Operator::NotContains => $query->{$boolean}($column, 'not like', '%'.$value.'%'),
            Operator::StartsWith => $query->{$boolean}($column, 'like', $value.'%'),
            Operator::EndsWith => $query->{$boolean}($column, 'like', '%'.$value),
            Operator::GreaterThan => $query->{$boolean}($column, '>', $value),
            Operator::GreaterThanOrEqual => $query->{$boolean}($column, '>=', $value),
            Operator::LessThan => $query->{$boolean}($column, '<', $value),
            Operator::LessThanOrEqual => $query->{$boolean}($column, '<=', $value),
            Operator::IsNull, Operator::NotExists => $query->{$boolean.'Null'}($column),
            Operator::IsNotNull, Operator::Exists => $query->{$boolean.'NotNull'}($column),
        };
    }

    private function resolveValue(
        string $source,
        mixed $value,
        User $actor,
        ?AuthorizationContext $context,
    ): mixed {
        return match ($source) {
            'literal' => $value,
            'actor' => $this->attributes->resolve($actor, 'user.'.ltrim((string) $value, '.')),
            'context' => $context?->get((string) $value),
            default => throw AuthorizationScopeException::forValueSource($source),
        };
    }
}
