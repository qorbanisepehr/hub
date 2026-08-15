<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
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
