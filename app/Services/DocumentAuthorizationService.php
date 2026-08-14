<?php

namespace App\Services;

use App\Contracts\DocumentAuthorization;
use App\Domains\Document\Auth\DocumentAuthorizationContext;
use App\Domains\Document\Enums\DocumentAction;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Adapts the current Role-based permissions to the Document authorization
 * contract. This is the only place that knows permission names — the Document
 * domain stays decoupled. Scope/policy dimensions (own vs all) arrive with the
 * policy phase and are resolved here (or in a replacement implementation),
 * never in Document code.
 */
class DocumentAuthorizationService implements DocumentAuthorization
{
    public function authorize(
        Authenticatable $actor,
        DocumentAction $action,
        DocumentAuthorizationContext $context,
    ): bool {
        if (! $actor instanceof User) {
            return false;
        }

        $permission = $this->permissionName($action);

        return $permission !== null && $actor->hasPermissionTo($permission);
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

    /**
     * Map an abstract action to its permission name.
     */
    private function permissionName(DocumentAction $action): ?string
    {
        return match ($action) {
            DocumentAction::View => 'employee.documents.view',
            DocumentAction::Download => 'employee.documents.download',
            DocumentAction::Upload => 'employee.documents.upload',
            DocumentAction::Delete, DocumentAction::Restore, DocumentAction::ForceDelete => 'employee.documents.delete',
            DocumentAction::Replace => 'employee.documents.upload',
            DocumentAction::LibrarySelect => 'employee.documents.library-select',
            DocumentAction::HistoryView, DocumentAction::HistoryDownload => null,
        };
    }
}
