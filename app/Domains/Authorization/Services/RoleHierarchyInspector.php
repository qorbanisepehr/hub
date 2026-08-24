<?php

namespace App\Domains\Authorization\Services;

use App\Domains\Authorization\Models\Role;
use Illuminate\Support\Facades\DB;

/**
 * Write-path guard for the role inheritance graph.
 *
 * The read side ({@see Role::getInheritedRoleIds()})
 * already traverses every parent edge cycle-safely; this inspector mirrors that
 * traversal for validation so a cycle can never be persisted — including cycles
 * that only close through a role's non-first parent edge (roles may have
 * multiple parents).
 */
final class RoleHierarchyInspector
{
    /** @var array<int, list<int>> Child role id => all of its parent ids. */
    private ?array $edges = null;

    /**
     * Would assigning {@see $candidateParentId} as a parent of {@see $roleId}
     * introduce a cycle — i.e., is the role reachable from the candidate by
     * walking UP the existing ancestor edges?
     */
    public function wouldCreateCycle(int $roleId, int $candidateParentId): bool
    {
        if ($candidateParentId === $roleId) {
            return true;
        }

        $seen = [$candidateParentId => true];
        $queue = [$candidateParentId];

        while ($queue !== []) {
            $current = array_shift($queue);

            foreach ($this->parentIdsOf($current) as $parentId) {
                if ($parentId === $roleId) {
                    return true;
                }

                if (! isset($seen[$parentId])) {
                    $seen[$parentId] = true;
                    $queue[] = $parentId;
                }
            }
        }

        return false;
    }

    /**
     * The whole edge map is loaded once per request; individual candidate
     * checks then cost a plain array walk.
     *
     * @return list<int>
     */
    private function parentIdsOf(int $roleId): array
    {
        if ($this->edges === null) {
            $this->edges = DB::table('role_inheritances')
                ->get(['role_id', 'parent_role_id'])
                ->groupBy('role_id')
                ->map(fn ($rows) => $rows->pluck('parent_role_id')->map(intval(...))->values()->all())
                ->all();
        }

        return $this->edges[$roleId] ?? [];
    }
}
