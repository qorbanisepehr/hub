<?php

namespace App\Services;

use App\Contracts\DocumentAuthorization;
use App\Domains\Document\Auth\DocumentAuthorizationContext;
use App\Domains\Document\Enums\DocumentAction;
use App\Domains\Employee\Models\Employee;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Adapts the current Role-based permissions to the Document authorization
 * contract. This is the only place that knows RBAC permission names and the
 * own/all scope semantics — the Document domain stays decoupled. When the RBAC
 * phase introduces scope/policy dimensions, they are resolved here (or in a
 * replacement implementation), not in Document code.
 */
class DocumentAuthorizationService implements DocumentAuthorization
{
    public function authorize(
        Authenticatable $actor,
        DocumentAction $action,
        DocumentAuthorizationContext $context,
    ): bool {
        if ($actor instanceof User && $actor->isSuperAdmin()) {
            return true;
        }

        if (! $actor instanceof User) {
            return false;
        }

        if ($context->owner instanceof Employee) {
            return $this->authorizeEmployeeDocument($actor, $action, $context);
        }

        return $this->authorizeStandaloneDocument($actor, $action, $context);
    }

    public function scope(
        Authenticatable $actor,
        DocumentAction $action,
        Builder $query,
    ): Builder {
        $query
            ->whereNull('document_usages.deleted_at')
            ->whereHas('document', fn (Builder $q) => $q->whereNull('documents.deleted_at'));

        return $query;
    }

    private function authorizeEmployeeDocument(
        User $actor,
        DocumentAction $action,
        DocumentAuthorizationContext $context,
    ): bool {
        $group = match ($action) {
            DocumentAction::Upload,
            DocumentAction::Replace,
            DocumentAction::Delete,
            DocumentAction::Restore,
            DocumentAction::ForceDelete => 'update',
            default => 'view',
        };

        if (! $this->hasScopedPermission($actor, "employee.{$group}_own", "employee.{$group}_all", false)) {
            return false;
        }

        // Category constraints are a policy dimension (rule: document privacy
        // and authorization). They arrive with the RBAC phase — today every
        // reachable category is allowed, the contract already carries it.
        if ($action === DocumentAction::LibrarySelect) {
            return $actor->hasPermissionTo('document.library-select');
        }

        return true;
    }

    private function authorizeStandaloneDocument(
        User $actor,
        DocumentAction $action,
        DocumentAuthorizationContext $context,
    ): bool {
        [$own, $all] = $this->permissionNames($action);

        // Ownership tracking (document.uploaded_by) does not exist yet, so the
        // own scope can never match. Grant only on the `all` permission.
        return $this->hasScopedPermission($actor, $own, $all, false);
    }

    /**
     * Map an abstract action to the current own/all permission names.
     *
     * @return array{0: string|null, 1: string|null} [own, all]
     */
    private function permissionNames(DocumentAction $action): array
    {
        return match ($action) {
            DocumentAction::View => ['document.view_own', 'document.view_all'],
            DocumentAction::Download => ['document.download_own', 'document.download_all'],
            DocumentAction::Upload => ['document.upload_own', 'document.upload_all'],
            DocumentAction::Replace => [null, null],
            DocumentAction::Delete, DocumentAction::Restore, DocumentAction::ForceDelete => ['document.delete_own', 'document.delete_all'],
            DocumentAction::LibrarySelect => [null, 'document.library-select'],
            DocumentAction::HistoryView, DocumentAction::HistoryDownload => [null, null],
        };
    }

    private function hasScopedPermission(User $actor, ?string $own, ?string $all, bool $ownsResource): bool
    {
        if ($all !== null && $actor->hasPermissionTo($all)) {
            return true;
        }

        if ($own === null || ! $actor->hasPermissionTo($own)) {
            return false;
        }

        return $ownsResource;
    }
}
