<?php

namespace App\Domains\Authorization\Services;

use App\Contracts\Authorization;
use App\Domains\Authorization\Engine\AuthorizationDecision;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Config;

/**
 * Field-level authorization for resource responses.
 *
 * Field authorization is deny-based: a field group (see
 * config/authorization-fields.php) is visible whenever the actor may access
 * the resource, and is stripped only when the actor's active role chain
 * carries an explicit DENY rule for the group's `.view` permission. The
 * resource stays the security boundary — the frontend never receives the
 * denied fields, so it cannot merely hide them.
 */
final class FieldAccess
{
    public function __construct(
        private readonly Authorization $authorization,
    ) {}

    /**
     * Remove every response key whose field group is explicitly denied for the
     * given actor and resource.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public function filter(Authenticatable $actor, string $resourceType, mixed $resource, array $fields): array
    {
        $groups = Config::get("authorization-fields.groups.{$resourceType}", []);

        foreach ($groups as $definition) {
            $permission = $definition['permission'] ?? null;
            $groupFields = $definition['fields'] ?? [];

            if ($permission === null || $groupFields === []) {
                continue;
            }

            $decision = $this->authorization->explain($actor, $permission, $resource);

            if ($decision->reason === AuthorizationDecision::REASON_EXPLICIT_DENY) {
                $fields = array_diff_key($fields, array_flip($groupFields));
            }
        }

        return $fields;
    }
}
