<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Gate;

abstract class ApiController extends BaseController
{
    /**
     * The model class this controller operates on.
     * Override in child controllers (e.g., protected string $model = Employee::class).
     */
    protected ?string $model = null;

    /**
     * Maps controller method names to policy ability names.
     * Override to customize.
     *
     * @var array<string, string>
     */
    protected array $actionPermissions = [
        'index' => 'viewAny',
        'show' => 'view',
        'store' => 'create',
        'update' => 'update',
        'destroy' => 'delete',
    ];

    /**
     * Auto-authorize via the model's policy before the controller method runs.
     */
    public function callAction($method, $parameters): mixed
    {
        $ability = $this->actionPermissions[$method] ?? null;

        if ($ability !== null && $this->model !== null) {
            $target = $this->resolveModelFromParameters($parameters) ?? new $this->model;

            Gate::forUser(request()->user())->authorize($ability, $target);
        }

        return parent::callAction($method, $parameters);
    }

    /**
     * Apply own/all scope to a query based on the model's policy.
     * Call this in index/trashed methods.
     */
    protected function scopeQuery(Builder|Relation $query, Request $request, string $ownerColumn, ?string $userColumn = 'id'): void
    {
        if ($this->model === null) {
            return;
        }

        if (Gate::forUser($request->user())->allows('scopeOwn', new $this->model)) {
            $query->where($ownerColumn, $request->user()->$userColumn);
        }
    }

    /**
     * Try to extract a model instance from route-bound parameters.
     */
    protected function resolveModelFromParameters(array $parameters): ?Model
    {
        foreach ($parameters as $parameter) {
            if ($parameter instanceof Model) {
                return $parameter;
            }
        }

        return null;
    }
}
