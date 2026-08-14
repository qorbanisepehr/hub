<?php

namespace App\Domains\Document\Services;

use App\Contracts\Documentable;
use App\Contracts\DocumentAuthorization;
use App\Domains\Document\Auth\DocumentAuthorizationContext;
use App\Domains\Document\Enums\DocumentAction;
use Illuminate\Contracts\Auth\Authenticatable;

class DocumentCapabilities
{
    /** @var array<string, DocumentAction> */
    private const ACTION_MAP = [
        'view' => DocumentAction::View,
        'download' => DocumentAction::Download,
        'upload' => DocumentAction::Upload,
        'replace' => DocumentAction::Replace,
        'delete' => DocumentAction::Delete,
        'restore' => DocumentAction::Restore,
        'force_delete' => DocumentAction::ForceDelete,
        'library_select' => DocumentAction::LibrarySelect,
    ];

    public function __construct(
        private readonly DocumentAuthorization $authorization,
    ) {}

    /**
     * Backend-authoritative set of document actions allowed for an entity. The
     * frontend must not derive these rules from the entity type itself.
     *
     * For authenticated employee flows the business capability is intersected
     * with the authorization decision, so capabilities may differ per user,
     * Employee, category, placement and state. Grant-based flows (cv /
     * questionnaire) have no authenticated actor and keep their business
     * capability only — their access is governed by the grant itself.
     *
     * @return array<string, bool>
     */
    public function forEntity(?Authenticatable $actor, Documentable $entity): array
    {
        $business = $this->forRouteType($entity->getDocumentRouteType());

        if ($actor === null || $entity->getDocumentRouteType() !== 'employee') {
            return $business;
        }

        return $this->intersectWithAuthorization($actor, $entity, $business);
    }

    /**
     * Business lifecycle capability per route type. These describe what the
     * product supports for the entity, independent of authorization.
     *
     * @return array<string, bool>
     */
    public function forRouteType(string $routeType): array
    {
        return match ($routeType) {
            'employee' => [
                'view' => true,
                'download' => true,
                'upload' => true,
                'replace' => true,
                'delete' => true,
                'restore' => true,
                'force_delete' => true,
                'history' => true,
                'library_select' => false,
            ],
            'cv', 'questionnaire' => [
                'view' => true,
                'download' => true,
                'upload' => true,
                'replace' => false,
                'delete' => true,
                'restore' => false,
                'force_delete' => false,
                'history' => false,
                'library_select' => false,
            ],
            default => [
                'view' => true,
                'download' => true,
                'upload' => true,
                'replace' => false,
                'delete' => true,
                'restore' => false,
                'force_delete' => false,
                'history' => false,
                'library_select' => false,
            ],
        };
    }

    /**
     * @param  array<string, bool>  $business
     * @return array<string, bool>
     */
    private function intersectWithAuthorization(
        Authenticatable $actor,
        Documentable $entity,
        array $business,
    ): array {
        $context = DocumentAuthorizationContext::forOwner($entity);

        $result = [];

        foreach ($business as $key => $allowed) {
            $action = self::ACTION_MAP[$key] ?? null;

            if (! $allowed) {
                $result[$key] = false;

                continue;
            }

            // Capabilities without an authorization dimension yet (e.g. the
            // deferred employee `history` feature) pass their business value
            // through untouched.
            if ($action === null) {
                $result[$key] = true;

                continue;
            }

            $result[$key] = $this->authorization->authorize($actor, $action, $context);
        }

        return $result;
    }
}
