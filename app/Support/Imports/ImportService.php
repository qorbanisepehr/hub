<?php

namespace App\Support\Imports;

use App\Support\Imports\Contract\NormalizesValues;
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
 * can present (dry-run) or persist. No format logic lives here (the
 * registry owns formats), no domain rules do (the validator owns them),
 * and no persistence does (the definition's persister does).
 */
final class ImportService
{
    public function __construct(
        private readonly ReaderRegistry $readers,
    ) {}

    /**
     * Dry-run: read the file, map it onto the accepted columns, normalize
     * human words to stored values, validate every mapped row, and return
     * the plan. One streaming pass, one row in memory at a time, nothing
     * persisted.
     *
     * @param  list<ImportColumn>  $accepted  The definition's accepted columns (order = template order).
     * @param  list<string>  $requiredKeys  Column keys a filled row must provide.
     * @param  list<string>  $requiredTemplateColumns  Column keys the FILE must carry (header-level check).
     * @param  NormalizesValues|null  $normalizer  Optional human-word → stored-value vocabulary.
     */
    public function dryRun(ImportSource $source, array $accepted, array $requiredKeys, ?RowValidator $validator = null, array $requiredTemplateColumns = [], ?NormalizesValues $normalizer = null): ImportPlan
    {
        $columns = $this->withRequired($accepted, $requiredKeys);

        $templateMeta = $this->metaFor($source);
        $schemaVersion = $templateMeta === null ? null : (int) ($templateMeta['_schema_version'] ?? 0);

        if ($templateMeta !== null && $schemaVersion !== 1) {
            throw new RuntimeException(
                "The template schema version [{$schemaVersion}] is not supported; re-download the template.",
            );
        }

        // Header aliases: the template's data sheet is written in the human
        // label language, and its _meta sheet carries key→label. Invert that
        // map so a Persian header translates back onto its catalog key.
        // Files without _meta (plain data files, CSV) are key-headed and
        // need no aliases. A label that no longer matches any catalog key
        // simply stays unknown — the report surfaces it. The normalizer
        // rides the mapper so human-word cells («بله») convert to stored
        // values BEFORE the cell typing, not after.
        $mapper = new ImportMapper($columns, $this->aliasesFromMeta($templateMeta, $columns), $normalizer);

        // Phase 1: the header names — mapping is validated before any data
        // row is touched (fail fast on wrong templates).
        $reader = $this->readers->get($source->format);
        $mapping = $mapper->mapHeaders($reader->headers($source->path));

        // The definition's file-level guard: template columns (the upsert
        // anchors) must at least be OFFERED by the file even though a row
        // may fill either one. Matched headers are already canonical keys.
        $missingTemplate = array_values(array_diff($requiredTemplateColumns, $mapping['matched']));

        if ($mapping['missing_required'] !== [] || $missingTemplate !== []) {
            throw new RuntimeException(
                'The file is missing required columns: '
                .implode(', ', array_values(array_unique(array_merge(
                    $mapping['missing_required'],
                    $missingTemplate,
                )))).'.',
            );
        }

        // Phase 2: the data rows (the reader consumed the header already).
        $rows = [];
        $rowIndexes = [];
        $rejected = [];
        $index = 0;

        foreach ($reader->read($source->path) as $cells) {
            $mapped = $mapper->mapRow($cells);

            if ($mapped === []) {
                $index++; // Blank line — not an error, not a row.

                continue;
            }

            $outcome = $validator === null
                ? ['ok' => true, 'row' => $mapped]
                : $validator->validate($mapped);

            if ($outcome['ok']) {
                $rows[] = $outcome['row'];
                $rowIndexes[] = $index;
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
            rowIndexes: $rowIndexes,
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
     * label → key aliases from the file's `_meta` pairs (the meta sheet is
     * the file's own translation table: key→label, so invert it). Only
     * labels of KNOWN catalog keys become aliases, and a label identical
     * to a real key never shadows that key.
     *
     * @param  array<string, string>|null  $templateMeta
     * @param  list<ImportColumn>  $columns
     * @return array<string, string>
     */
    private function aliasesFromMeta(?array $templateMeta, array $columns): array
    {
        if ($templateMeta === null) {
            return [];
        }

        $knownLabels = [];

        foreach ($columns as $column) {
            $knownLabels[$column->faLabel] = $column->key;
        }

        $aliases = [];

        foreach ($templateMeta as $key => $label) {
            if ($key === '_schema_version' || ! isset($knownLabels[$label])) {
                continue;
            }

            if ($key === $label) {
                continue;
            }

            $aliases[$label] = $knownLabels[$label];
        }

        return $aliases;
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
