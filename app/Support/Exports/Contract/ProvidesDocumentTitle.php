<?php

namespace App\Support\Exports\Contract;

/**
 * Optional capability: the human title a tabular exporter carries when the
 * kernel renders it as a printed document (PDF/Word). Without it the bridge
 * falls back to baseFilename() — a slug — so exporters that face humans
 * declare this and own their vocabulary (capability contracts degrade
 * silently, see the import/export rules).
 */
interface ProvidesDocumentTitle
{
    /**
     * Localized one-line title for the document header (no format words).
     */
    public function documentTitle(): string;
}
