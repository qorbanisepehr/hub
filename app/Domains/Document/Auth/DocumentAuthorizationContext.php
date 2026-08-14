<?php

namespace App\Domains\Document\Auth;

use App\Contracts\Documentable;
use App\Domains\Document\Models\Document;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Document\Models\DocumentUsage;

/**
 * The resource context a document operation runs against. The authorization
 * contract consumes only this context — it never sees roles, permissions, or
 * RBAC internals.
 */
final class DocumentAuthorizationContext
{
    public function __construct(
        public readonly ?Document $document = null,
        public readonly ?DocumentUsage $usage = null,
        public readonly ?Documentable $owner = null,
        public readonly ?DocumentCategory $category = null,
        public readonly ?string $sectionKey = null,
        public readonly ?string $fieldKey = null,
        public readonly bool $trashed = false,
    ) {}

    public static function forUsage(DocumentUsage $usage): self
    {
        $entity = $usage->resolveEntity();

        return new self(
            document: $usage->document,
            usage: $usage,
            owner: $entity instanceof Documentable ? $entity : null,
            category: $usage->document?->category,
            sectionKey: $usage->section_key,
            fieldKey: $usage->field_key,
            trashed: $usage->trashed(),
        );
    }

    public static function forDocument(Document $document): self
    {
        return new self(
            document: $document,
            category: $document->category,
        );
    }

    public static function forOwner(
        Documentable $owner,
        ?Document $document = null,
        ?DocumentCategory $category = null,
        ?string $sectionKey = null,
        ?string $fieldKey = null,
    ): self {
        return new self(
            document: $document,
            owner: $owner,
            category: $category,
            sectionKey: $sectionKey,
            fieldKey: $fieldKey,
        );
    }
}
