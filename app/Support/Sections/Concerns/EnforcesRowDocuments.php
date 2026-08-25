<?php

namespace App\Support\Sections\Concerns;

use App\Contracts\Documentable;
use App\Domains\Document\Models\DocumentCategory;
use App\Domains\Document\Models\DocumentUsage;
use Illuminate\Support\Collection;

/**
 * Generic submit-time enforcement of per-row document requirements.
 *
 * Sections that declare dynamicDocumentRequirements() for repeatable rows
 * use this trait to derive completionDocumentErrors() from those very
 * declarations — page counts live in exactly one place and the usage counts
 * are gathered with a single grouped query per section instead of one query
 * per (row, category) pair.
 *
 * Contract with the consuming section:
 *  - rowDocumentsRowsPath(): key of the rows array inside the section's
 *    JSONB storage column, e.g. "dependents" or "education_records". The
 *    completion error keys reuse it: "{section}.{rowsPath}.{index}.{slug}".
 *  - fieldKeyFor(int $index): placement field key of a row.
 */
trait EnforcesRowDocuments
{
    abstract public static function fieldKeyFor(int $index): string;

    abstract public function rowDocumentsRowsPath(): string;

    /**
     * Submit-time check that every stored row carries its declared required
     * pages. Counts active document usages per row placement against the
     * section's dynamic requirement declarations.
     *
     * @return array<string, string[]>
     */
    public function completionDocumentErrors(Documentable $entity): array
    {
        $rows = $this->rowDocumentsRows($entity);

        if ($rows === []) {
            return [];
        }

        $counts = $this->rowCountsByPlacement($entity);
        $categoryNames = DocumentCategory::query()
            ->whereIn('slug', $this->rowCategorySlugs())
            ->pluck('name', 'slug');

        $errors = [];

        foreach ($rows as $index => $row) {
            if (! is_numeric($index) || ! is_array($row)) {
                continue;
            }

            $index = (int) $index;
            $fieldKey = static::fieldKeyFor($index);
            $label = $this->documentFieldKeyLabel($entity, $fieldKey);

            foreach ($this->requirementsForFieldKey($fieldKey) as $slug => $requirement) {
                $minFiles = (int) ($requirement['min_files'] ?? 0);

                if (! ($requirement['required'] ?? false) || $minFiles < 1) {
                    continue;
                }

                $present = (int) ($counts["{$fieldKey}|{$slug}"] ?? 0);

                if ($present >= $minFiles) {
                    continue;
                }

                $errors["{$this->key()}.{$this->rowDocumentsRowsPath()}.{$index}.{$slug}"] = [
                    __('sections.row_documents_required', [
                        'category' => $categoryNames[$slug] ?? $slug,
                        'label' => $label ?? "#{$index}",
                        'min' => $minFiles,
                    ]),
                ];
            }
        }

        return $errors;
    }

    /**
     * Stored rows for this section's repeatable placements, keyed by index.
     *
     * @return array<int|string, mixed>
     */
    protected function rowDocumentsRows(Documentable $entity): array
    {
        $column = $this->storage()['jsonb'] ?? null;

        $rows = is_string($column)
            ? data_get($entity->{$column}, $this->rowDocumentsRowsPath())
            : null;

        return is_array($rows) ? $rows : [];
    }

    /**
     * Category slugs required by this section's row placements.
     *
     * @return list<string>
     */
    protected function rowCategorySlugs(): array
    {
        $slugs = [];

        foreach ($this->dynamicDocumentRequirements() as $requirements) {
            foreach (array_keys($requirements) as $slug) {
                $slugs[] = (string) $slug;
            }
        }

        return array_values(array_unique($slugs));
    }

    /**
     * Requirement map merged from every pattern matching the field key.
     *
     * @return array<string, array<string, mixed>>
     */
    private function requirementsForFieldKey(string $fieldKey): array
    {
        $matched = [];

        foreach ($this->dynamicDocumentRequirements() as $pattern => $requirements) {
            if (preg_match((string) $pattern, $fieldKey) === 1) {
                $matched += $requirements;
            }
        }

        return $matched;
    }

    /**
     * Active usage counts for this section keyed "{field_key}|{category_slug}".
     *
     * @return Collection<string, int>
     */
    private function rowCountsByPlacement(Documentable $entity): Collection
    {
        return DocumentUsage::query()
            ->selectRaw("concat_ws('|', document_usages.field_key, document_categories.slug) as placement")
            ->selectRaw('count(*) as aggregate')
            ->join('documents', 'documents.id', '=', 'document_usages.document_id')
            ->join('document_categories', 'document_categories.id', '=', 'documents.category_id')
            ->where('document_usages.entity_type', $entity::class)
            ->where('document_usages.entity_id', $entity->getKey())
            ->whereNull('document_usages.deleted_at')
            ->where('document_usages.section_key', $this->key())
            ->groupBy('placement')
            ->pluck('aggregate', 'placement');
    }
}
