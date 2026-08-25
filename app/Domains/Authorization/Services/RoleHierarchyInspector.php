<?php

namespace App\Domains\Authorization\Services;

use App\Domains\Authorization\Models\Role;
use Illuminate\Support\Facades\DB;

/**
 * Write-path guard for the role hierarchy.
 *
 * The read side ({@see Role::getInheritedRoleIds()}) already walks the parent
 * chain cycle-safely; this inspector mirrors that traversal for validation so
 * a cycle can never be persisted.
 */
final class RoleHierarchyInspector
{
    /** @var array<int, int|null> Role id => its direct parent id. */
    private ?array $edges = null;

    /**
     * Would assigning {@see $candidateParentId} as the parent of {@see $roleId}
     * introduce a cycle — i.e., is the role reachable from the candidate by
     * walking UP the existing ancestor chain?
     */
    public function wouldCreateCycle(int $roleId, int $candidateParentId): bool
    {
        if ($candidateParentId === $roleId) {
            return true;
        }

        $seen = [$candidateParentId => true];
        $current = $candidateParentId;

        while (($current = $this->parentIdOf($current)) !== null) {
            if ($current === $roleId) {
                return true;
            }

            if (isset($seen[$current])) {
                break;
            }
            $seen[$current] = true;
        }

        return false;
    }

    /**
     * The whole id => parent_id map is loaded once per request; individual
     * candidate checks then cost a plain array walk.
     */
    private function parentIdOf(int $roleId): ?int
    {
        if ($this->edges === null) {
            $this->edges = DB::table('roles')
                ->pluck('parent_id', 'id')
                ->map(fn ($parentId) => $parentId !== null ? (int) $parentId : null)
                ->all();
        }

        return $this->edges[$roleId] ?? null;
    }
}
