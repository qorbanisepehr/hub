<?php

namespace App\Services;

use App\Contracts\Authorization;
use App\Contracts\Documentable;
use App\Contracts\DocumentAuthorization;
use App\Domains\Authorization\Engine\AuthorizationContext;
use App\Domains\Document\Auth\DocumentAuthorizationContext;
use App\Domains\Document\Enums\DocumentAction;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Adapts the Authorization engine to the Document contract. The Document domain
 * only ever sees actions + context; permission names, policies, and RBAC tables
 * stay behind this adapter. The engine decides everything: capability (an allow
 * rule) and restriction (a policy evaluated against the document/usage resource
 * or translated into query constraints by scope()).
 */
class DocumentAuthorizationService implements DocumentAuthorization
{
    public function __construct(
        private readonly Authorization $authorization,
    ) {}

    public function authorize(
        Authenticatable $actor,
        DocumentAction $action,
        DocumentAuthorizationContext $context,
    ): bool {
        if (! $actor instanceof User) {
            return false;
        }

        $permission = $this->permissionName($action, $context->owner);

        if ($permission === null) {
            return false;
        }

        $resource = $context->usage ?? $context->document;

        return $this->authorization->can(
            $actor,
            $permission,
            $resource,
            $this->engineContext($context),
        );
    }

    public function scope(
        Authenticatable $actor,
        DocumentAction $action,
        Builder $query,
        bool $trashed = false,
    ): Builder {
        if (! $trashed) {
            $query->whereNull('document_usages.deleted_at');
        }

        $query->whereHas('document', fn (Builder $q) => $q->whereNull('documents.deleted_at'));

        if (! $actor instanceof User) {
            return $query;
        }

        $permission = $this->permissionName($action, null);

        if ($permission === null) {
            return $query;
        }

        return $this->authorization->scope($actor, $permission, $query);
    }

    /**
     * Expose the operation's context to policy evaluation as `context.*` values.
     */
    private function engineContext(DocumentAuthorizationContext $context): AuthorizationContext
    {
        $values = [];

        if ($context->owner !== null) {
            $values['owner_id'] = $context->owner->getKey();
            $values['owner_type'] = get_class($context->owner);
        }

        if ($context->category !== null) {
            $values['category_id'] = $context->category->getKey();
            $values['category_slug'] = $context->category->slug;
        }

        $values['section_key'] = $context->sectionKey;
        $values['field_key'] = $context->fieldKey;
        $values['trashed'] = $context->trashed;

        return AuthorizationContext::make(array_filter(
            $values,
            fn (mixed $value) => $value !== null,
        ));
    }

    /**
     * Map an abstract action to its permission name. The permission namespace
     * follows the owning entity's route type so CV and questionnaire documents
     * stay under their own lifecycle and privacy permissions, independent of
     * employee documents.
     */
    private function permissionName(DocumentAction $action, ?Documentable $owner): ?string
    {
        $suffix = match ($action) {
            DocumentAction::View => 'view',
            DocumentAction::Download => 'download',
            DocumentAction::Upload => 'upload',
            DocumentAction::Replace => 'replace',
            DocumentAction::Delete => 'delete',
            DocumentAction::Restore => 'restore',
            DocumentAction::ForceDelete => 'force-delete',
            DocumentAction::LibrarySelect => 'library-select',
            DocumentAction::HistoryView => 'history-view',
            DocumentAction::HistoryDownload => 'history-download',
        };

        if ($suffix === null) {
            return null;
        }

        $prefix = match ($owner?->getDocumentRouteType()) {
            'cv' => 'cv.documents',
            'questionnaire' => 'questionnaire.documents',
            default => 'employee.documents',
        };

        return "{$prefix}.{$suffix}";
    }
}
