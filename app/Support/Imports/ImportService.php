<?php

namespace App\Support\Imports;

use App\Support\Imports\Contract\ReadsTemplateMeta;
use App\Support\Imports\Contract\RowValidator;
use App\Support\Imports\Value\ImportColumn;
use App\Support\Imports\Value\ImportPlan;
use App\Support\Imports\Value\ImportRowError;
use App\Support\Imports\Value\ImportSource;
use InvalidArgumentException;
use RuntimeException;

/**
 * The import kernel orchestrator — the mirror of `ExportService`: resolve
 * the reader for the file's format, stream rows through the mapper, hand
 * each mapped row to the domain's validator, and collect a plan the caller
 * can present (dry-run) or persist (later slice). No format logic lives
 * here (the registry owns formats), no domain rules do (the validator owns
 * them), and no persistence does (that is M2 slice 2's job).
 */
final class ImportService
{
    public function __construct(
        private readonly ReaderRegistry $readers,
    ) {}

    /**
     * Dry-run: read the file, map it onto the accepted columns, validate
     * every mapped row, and return the plan. One streaming pass, one row
     * in memory at a time, nothing persisted.
     *
     * @param  list<ImportColumn>  $accepted  The definition's accepted columns (order = template order).
     * @param  list<string>  $requiredKeys  Column keys a filled row must provide.
     */
    public function dryRun(ImportSource $source, array $accepted, array $requiredKeys, ?RowValidator $validator = null): ImportPlan
    {
        $columns = $this->withRequired($accepted, $requiredKeys);
        $mapper = new ImportMapper($columns);

        $templateMeta = $this->metaFor($source);
        $schemaVersion = $templateMeta === null ? null : (int) ($templateMeta['_schema_version'] ?? 0);

        if ($templateMeta !== null && $schemaVersion !== 1) {
            throw new RuntimeException(
                "The template schema version [{$schemaVersion}] is not supported; re-download the template.",
            );
        }

        // Phase 1: the header names — mapping is validated before any data
        // row is touched (fail fast on wrong templates).
        $reader = $this->readers->get($source->format);
        $mapping = $mapper->mapHeaders($reader->headers($source->path));

        if ($mapping['missing_required'] !== []) {
            throw new RuntimeException(
                'The file is missing required columns: '
                .implode(', ', $mapping['missing_required']).'.',
            );
        }

        // Phase 2: the data rows (the reader consumed the header already).
        $rows = [];
        $rejected = [];
        $index = 0;

        foreach ($reader->read($source->path) as $cells) {
            $mapped = $mapper->mapRow($cells);

            if ($mapped === []) {
                continue; // Blank line — not an error, not a row.
            }

            $outcome = $validator === null
                ? ['ok' => true, 'row' => $mapped]
                : $validator->validate($mapped);

            if ($outcome['ok']) {
                $rows[] = $outcome['row'];
            } else {
                $rejected[] = new ImportRowError($index, $index + 2, $outcome['errors']);
            }

            $index++;
        }

        return new ImportPlan(
            source: [
                'format' => $source->format,
                'file' => $source->originalName,
                'schema_version' => $schemaVersion,
                'template' => $templateMeta !== null,
            ],
            mapping: $mapping,
            rows: $rows,
            rejected: $rejected,
            total: $index,
        );
    }

    /**
     * The template's `_meta` pairs when the format can carry them; null
     * means "no meta in this file" (plain data file or meta-less format) —
     * never fatal by itself, the schema guard simply does not apply.
     *
     * @return array<string, string>|null
     */
    private function metaFor(ImportSource $source): ?array
    {
        $reader = $this->readers->get($source->format);

        if (! $reader instanceof ReadsTemplateMeta) {
            return null;
        }

        return $reader->readMeta($source->path);
    }

    /**
     * Stamp the required flags onto the accepted columns once, in one
     * place, so both the mapper and the report see the same definition.
     *
     * @param  list<ImportColumn>  $accepted
     * @param  list<string>  $requiredKeys
     * @return list<ImportColumn>
     */
    private function withRequired(array $accepted, array $requiredKeys): array
    {
        foreach ($accepted as $column) {
            if (! $column instanceof ImportColumn) {
                throw new InvalidArgumentException('Accepted columns must be ImportColumn instances.');
            }
        }

        if ($requiredKeys === []) {
            return $accepted;
        }

        return array_map(
            fn (ImportColumn $column) => $column->required || in_array($column->key, $requiredKeys, true)
                ? new ImportColumn($column->key, $column->faLabel, $column->type, true)
                : $column,
            $accepted,
        );
    }
}
