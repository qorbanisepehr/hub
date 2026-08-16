<?php

namespace App\Domains\Authorization\Attributes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

/**
 * Resolves policy attribute keys (e.g. `employee.employment_status`) to concrete values
 * on resource models. Definitions come from config/authorization-attributes.php.
 *
 * The engine stays independent of the domains: it only asks this registry
 * "what is the value of this attribute on this model?".
 */
final class AttributeRegistry
{
    /** @var array<string, array<string, AuthorizationAttribute>> */
    private array $attributes = [];

    public function __construct()
    {
        foreach (Config::get('authorization-attributes.attributes', []) as $resourceType => $definitions) {
            foreach ($definitions as $key => $definition) {
                $this->attributes[$resourceType][$key] = new AuthorizationAttribute(
                    key: $key,
                    label: $definition['label'] ?? $key,
                    type: $definition['type'] ?? 'string',
                    queryable: $definition['queryable'] ?? false,
                    column: $definition['column'] ?? null,
                    relations: $definition['relations'] ?? [],
                );
            }
        }
    }

    public function resourceTypeFor(?Model $model): ?string
    {
        if ($model === null) {
            return null;
        }

        $type = Config::get('authorization-attributes.models.'.get_class($model));

        return is_string($type) ? $type : null;
    }

    /**
     * Resolve the model class registered for a resource type key (e.g. "employee").
     */
    public function modelForResourceType(string $resourceType): ?string
    {
        foreach (Config::get('authorization-attributes.models', []) as $modelClass => $type) {
            if ($type === $resourceType) {
                return $modelClass;
            }
        }

        return null;
    }

    public function has(string $resourceType, string $key): bool
    {
        return isset($this->attributes[$resourceType][$key]);
    }

    public function definition(string $resourceType, string $key): ?AuthorizationAttribute
    {
        return $this->attributes[$resourceType][$key] ?? null;
    }

    /** @return array<string, AuthorizationAttribute> */
    public function all(string $resourceType): array
    {
        return $this->attributes[$resourceType] ?? [];
    }

    /**
     * Resolve the value of a policy attribute key against a model. The key may
     * be a full key (`employee.employment_status`) or a bare path; the path can
     * traverse relationships (`document.category.slug`).
     */
    public function resolve(Model $model, string $key): mixed
    {
        $segments = explode('.', $key);

        if (count($segments) > 1 && $segments[0] === $this->resourceTypeFor($model)) {
            array_shift($segments);
        }

        $current = $model;

        foreach ($segments as $segment) {
            if (! $current instanceof Model) {
                return null;
            }

            $current = $current->getAttribute($segment);
        }

        if ($current instanceof \BackedEnum) {
            return $current->value;
        }

        return $current;
    }
}
