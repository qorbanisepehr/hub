<?php

namespace App\Domains\Authorization\Policies;

use App\Contracts\Authorization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DynamicPolicy
{
    private const KNOWN_ABILITIES = ['viewAny', 'view', 'create', 'update', 'delete', 'scopeOwn', 'upload', 'download'];

    /**
     * Resolve the full config for a model.
     *
     * Resolution order:
     * 1. Explicit config in config('policies.models.*')
     * 2. Convention (class basename → kebab) + general template
     * 3. Null = deny all (group not found)
     */
    protected function getConfig(Model $model): ?array
    {
        $class = get_class($model);

        $explicit = config("policies.models.{$class}");
        if ($explicit !== null) {
            return $explicit;
        }

        $group = $this->resolveGroup($model);

        if ($group === null) {
            return null;
        }

        if (config("permissions.groups.{$group}") === null) {
            return null;
        }

        $general = config('policies.general');

        $permissions = array_map(
            fn (mixed $perm) => $this->fillGroup($perm, $group),
            $general['permissions'],
        );

        return [
            'owner_field' => $general['owner_field'],
            'permissions' => $permissions,
        ];
    }

    /**
     * Resolve the permission group name for a model via convention.
     */
    protected function resolveGroup(Model $model): ?string
    {
        return Str::kebab(class_basename($model));
    }

    /**
     * Replace {group} placeholder in permission definitions.
     *
     * @param  string|array  $value
     * @return string|array
     */
    protected function fillGroup(mixed $value, string $group): mixed
    {
        if (is_string($value)) {
            return str_replace('{group}', $group, $value);
        }

        if (is_array($value)) {
            return array_map(
                fn (mixed $v) => $this->fillGroup($v, $group),
                $value,
            );
        }

        return $value;
    }

    protected function resolveMethod(User $user, Model $model, string $method): bool
    {
        $config = $this->getConfig($model);

        if ($config === null) {
            return false;
        }

        $permission = $config['permissions'][$method] ?? null;

        if ($permission === null) {
            return false;
        }

        // Resource-bound abilities evaluate the policy against the route-bound
        // model. List/create-style abilities have no concrete resource and are
        // capability-level checks; row-level visibility is scope()'s job.
        $resource = in_array($method, ['view', 'update', 'delete', 'download', 'upload'], true)
            ? $model
            : null;

        return $this->resolve($user, $permission, $model, $config, $resource);
    }

    protected function resolve(User $user, mixed $permission, Model $model, ?array $config = null, ?Model $resource = null): bool
    {
        return match (true) {
            is_string($permission) => app(Authorization::class)->can($user, $permission, $resource),
            is_array($permission) => $user->hasAnyPermission($permission),
            default => false,
        };
    }

    public function viewAny(User $user, Model $model): bool
    {
        return $this->resolveMethod($user, $model, 'viewAny');
    }

    public function view(User $user, Model $model): bool
    {
        return $this->resolveMethod($user, $model, 'view');
    }

    public function create(User $user, Model $model): bool
    {
        return $this->resolveMethod($user, $model, 'create');
    }

    public function update(User $user, Model $model): bool
    {
        return $this->resolveMethod($user, $model, 'update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->resolveMethod($user, $model, 'delete');
    }

    /**
     * Own-scope filtering is handled by the Authorization engine's scope()
     * query translation, not by a policy ability. This ability always resolves
     * to false so ApiController::scopeQuery stays a no-op.
     */
    public function scopeOwn(User $user, Model $model): bool
    {
        return false;
    }

    /**
     * Public entry point for Gate::before() — resolves ability for any model.
     *
     * @return bool|null true=allow, false=deny, null=not handled
     */
    public function check(User $user, string $ability, Model $model): ?bool
    {
        $config = $this->getConfig($model);

        if ($config === null) {
            return null;
        }

        return $this->resolveMethod($user, $model, $ability);
    }

    public function __call(string $method, array $arguments): bool
    {
        if (! in_array($method, self::KNOWN_ABILITIES, true)) {
            throw new \BadMethodCallException("Unknown ability: {$method}");
        }

        $user = $arguments[0];
        $model = $arguments[1] ?? null;

        if ($model instanceof Model) {
            return $this->resolveMethod($user, $model, $method);
        }

        return false;
    }
}
