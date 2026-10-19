<?php

namespace App\Support\Exports\Contract;

use App\Support\Exports\Value\Document\DocumentSpec;

/**
 * The domain-side port for single-record documents (an employee profile, a
 * questionnaire, a CV): the domain owns WHAT the document contains and
 * builds the kernel DocumentSpec from its entity; the renderers own HOW it
 * becomes PDF/Word bytes. Implementations must never touch a renderer.
 */
interface DocumentSource
{
    /**
     * The document content for this entity, already presented for human
     * readers (localized labels, Jalali dates, Persian digits).
     */
    /**
     * @param  list<string>|null  $sections  the parts to include, IN ORDER, or null to
     *                                       use whatever this source would choose on its
     *                                       own. Optional rather than required because
     *                                       most sources have one sensible answer and
     *                                       widening it lets an export dialog offer a
     *                                       different one for a single request without
     *                                       every source having to grow a second method
     *                                       for a feature only some of them have.
     */
    public function document(mixed $entity, ?array $sections = null): DocumentSpec;

    /**
     * Filename base, e.g. 'employee-profile'; the kernel composes
     * `{base}-{Ymd-His}.{extension}`.
     */
    public function baseFilename(): string;
}
