<?php

namespace App\Domains\Document\Services;

use App\Contracts\Documentable;

class DocumentCapabilities
{
    /**
     * Backend-authoritative set of document actions allowed for an entity.
     * The frontend must not derive these rules from the entity type itself.
     *
     * @return array<string, bool>
     */
    public function forEntity(Documentable $entity): array
    {
        return $this->forRouteType($entity->getDocumentRouteType());
    }

    /** @return array<string, bool> */
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
}
