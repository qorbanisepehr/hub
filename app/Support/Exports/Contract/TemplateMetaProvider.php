<?php

namespace App\Support\Exports\Contract;

/**
 * Optional exporter capability: the exporter knows the meta a fill-and-import
 * template must carry (schema version, key → label map). ExportService reads
 * this only when the chosen writer can host a meta sheet (e.g. xlsx); other
 * formats simply get a header-only file.
 */
interface TemplateMetaProvider
{
    /**
     * Ordered `key => value` pairs for the template's meta sheet. Keys
     * starting with `_` are metadata (e.g. `_schema_version`); every other
     * pair maps a column key to its human label.
     *
     * @return array<string, string>
     */
    public function templateMeta(): array;
}
