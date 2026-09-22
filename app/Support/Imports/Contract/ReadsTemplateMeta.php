<?php

namespace App\Support\Imports\Contract;

/**
 * Optional reader capability: the format can carry the template's `_meta`
 * sheet (xlsx today). Mirrors `TemplateWriter`/`TemplateMetaProvider` on
 * the export side — capability pairs keep kernel and domain code free of
 * format checks (formats without the capability degrade gracefully).
 */
interface ReadsTemplateMeta
{
    /**
     * The template's `_meta` key → value pairs, or null when the file has
     * no meta sheet (plain data file or meta-less format).
     *
     * @return array<string, string>|null
     */
    public function readMeta(string $path): ?array;
}
