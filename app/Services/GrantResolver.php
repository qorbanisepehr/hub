<?php

namespace App\Services;

use App\Contracts\OtpVerifiable;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Resolves grant entities from the config/grants.php registry.
 *
 * Keeps middleware and controllers generic over entities; there is no
 * per-entity `match` anywhere in the grant-access flow.
 */
class GrantResolver
{
    /**
     * The registry entry for the given entity, or null when unknown.
     *
     * @return array{model: class-string<OtpVerifiable>, channel: string}|null
     */
    public function config(string $entity): ?array
    {
        $config = config("grants.entities.{$entity}");

        return is_array($config) ? $config : null;
    }

    /**
     * Load the OtpVerifiable instance identified by an entity + uuid.
     */
    public function resolve(string $entity, string $uuid): OtpVerifiable
    {
        $config = $this->config($entity);

        if (! $config) {
            throw new \InvalidArgumentException("Unknown grant entity [{$entity}].");
        }

        /** @var class-string<OtpVerifiable> $model */
        $model = $config['model'];

        $instance = $model::query()->where('uuid', $uuid)->first();

        if (! $instance) {
            throw (new ModelNotFoundException)->setModel($model, [$uuid]);
        }

        return $instance;
    }
}
