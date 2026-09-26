<?php

namespace App\Support\Exports\Documents;

use App\Rules\FormOptionValue;
use App\Support\Exports\Value\Document\DocumentField;
use App\Support\Exports\Value\Document\DocumentSection;
use App\Support\Exports\Value\Document\DocumentTable;
use App\Support\Exports\Value\ExportColumn;
use App\Support\Exports\Value\ExportColumnType;
use App\Support\Exports\Value\PresentationOptions;
use App\Support\Exports\Value\ValuePresentation;
use App\Support\Exports\ValuePresenter;
use App\Support\Sections\SectionDefinition;
use App\Support\Sections\SectionService;
use Illuminate\Support\Arr;

/**
 * Builds a kernel DocumentSpec from a SectionService and one entity: the
 * same section data the completion validator sees (gatherAllData), walked in
 * the sections' own order, each leaf value shaped for human readers through
 * the shared ValuePresenter (Jalali dates, Persian digits, booleans).
 *
 * Field labels are TRANSLATION-FIRST exactly like the export catalog: the
 * dotted `section.field` key first, then the bare leaf, then the dotted key
 * itself as a VISIBLE fallback so a missing translation shows up instead of
 * silently blanking. Option-valued cells resolve through the caller-supplied
 * label callback (form-options groups) so this stays entity-agnostic: the
 * domain owns its vocabulary source, the builder only asks "what label does
 * this value have, under this option group (if any)".
 *
 * Repeater values (lists) render as a table with one row per entry, their
 * cells typed by the section's own `field.*.leaf` rules; sub-map values are
 * reached through dotted rule keys (military_status.status, address.province)
 * exactly like the exporter does.
 */
final class SectionDocumentBuilder
{
    /**
     * Documents read for people: localized labels, Jalali calendar, Persian digits.
     */
    private const PRESENTATION = [
        'headers' => 'label',
        'calendar' => 'persian',
        'digits' => 'persian',
    ];

    /**
     * @param  array<string, array<string, string>>  $labelMaps  Translation maps consulted first, in priority order.
     * @param  ?callable(string, string, ?string):(?string)  $optionLabel  (dottedKey, storedValue, group|null) => localized label | null.
     * @param  list<string>|null  $onlySections  Section-key allowlist (field access); null = all registered sections.
     */
    public function __construct(
        private readonly SectionService $sections,
        private readonly array $labelMaps,
        private readonly ?\Closure $optionLabel = null,
        private readonly ?array $onlySections = null,
    ) {}

    /**
     * The printable sections of one entity, in registry order.
     *
     * @return list<DocumentSection>
     */
    public function build(mixed $entity): array
    {
        $data = $this->sections->gatherAllData($entity);
        $built = [];

        foreach ($this->sections->sections() as $sectionKey => $section) {
            if ($this->onlySections !== null && ! in_array($sectionKey, $this->onlySections, true)) {
                continue;
            }

            $document = $this->buildSection($sectionKey, $section, (array) ($data[$sectionKey] ?? []));

            if (! $document->isEmpty()) {
                $built[] = $document;
            }
        }

        return $built;
    }

    /**
     * @param  array<string, mixed>  $sectionData
     */
    private function buildSection(string $sectionKey, SectionDefinition $section, array $sectionData): DocumentSection
    {
        $rules = $section->structuralRules();
        $fields = [];
        $tables = [];

        foreach ($rules as $field => $rule) {
            $value = Arr::get($sectionData, $field);

            if (is_array($value)) {
                // A nested map (military_status, address) must NOT be printed
                // as a joined line: that leaks stored slugs/codes with a
                // slug label. Its dotted leaves are printed individually by
                // their own rules below. (array_values() would make any map
                // look like a list, so the check must happen first.)
                if (! array_is_list($value)) {
                    continue;
                }

                $entries = array_values(array_filter($value, static fn (mixed $entry): bool => $entry !== null));

                if ($entries !== [] && ! is_array($entries[0])) {
                    // Scalar-value list (e.g. preferred_workplace.*): one line
                    // joining every resolved entry value.
                    $cells = array_map(
                        fn (mixed $entry): string => (string) ($this->presentLeaf("{$sectionKey}.{$field}.*", (string) $entry, $rules["{$field}.*"] ?? $rule) ?? $entry),
                        $entries,
                    );

                    $documentField = DocumentField::from($this->labelFor($sectionKey, $field), implode('، ', $cells));

                    if ($documentField !== null) {
                        $fields[] = $documentField;
                    }

                    continue;
                }

                $table = $this->buildTable($sectionKey, $field, $value, $rules);

                if ($table !== null && ! $table->isEmpty()) {
                    $tables[] = $table;
                }

                continue;
            }

            $presented = $this->presentLeaf("{$sectionKey}.{$field}", $value, $rule);
            $documentField = DocumentField::from($this->labelFor($sectionKey, $field), $presented);

            if ($documentField !== null) {
                $fields[] = $documentField;
            }
        }

        return new DocumentSection(
            heading: $section->label(),
            fields: $fields,
            tables: $tables,
        );
    }

    /**
     * A repeater value: one column per union of entry keys, one row per
     * entry, each cell typed by the section's own `field.*.leaf` rule when
     * the section declares one (dates go Jalali like every other print).
     *
     * @param  array<array-key, mixed>  $entries
     * @param  array<string, string|array>  $rules
     */
    private function buildTable(string $sectionKey, string $field, array $entries, array $rules): ?DocumentTable
    {
        $maps = array_values(array_filter($entries, fn ($entry): bool => is_array($entry)));

        if ($maps === []) {
            return null;
        }

        $leaves = [];

        foreach ($maps as $entry) {
            foreach (array_keys($entry) as $leaf) {
                if (is_string($leaf) && ! in_array($leaf, $leaves, true)) {
                    $leaves[] = $leaf;
                }
            }
        }

        if ($leaves === []) {
            return null;
        }

        $headers = array_map(
            fn (string $leaf): string => $this->labelFor($sectionKey, "{$field}.*.{$leaf}"),
            $leaves,
        );

        $rows = [];

        foreach ($maps as $entry) {
            $cells = [];

            foreach ($leaves as $leaf) {
                $rule = $rules["{$field}.*.{$leaf}"] ?? 'nullable';
                $cells[] = (string) ($this->presentLeaf("{$sectionKey}.{$field}.*.{$leaf}", $entry[$leaf] ?? null, $rule) ?? '');
            }

            $rows[] = $cells;
        }

        return new DocumentTable(
            caption: $this->labelFor($sectionKey, $field),
            headers: $headers,
            rows: $rows,
        );
    }

    /**
     * Shape one stored leaf for print, or null when it has no printable value.
     */
    private function presentLeaf(string $dottedKey, mixed $value, string|array|null $rule): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m-d');
        } elseif (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        if (! is_scalar($value)) {
            return null;
        }

        $flat = is_string($rule) ? $rule : implode('|', array_filter((array) $rule, 'is_string'));
        $group = $this->optionGroup($rule);
        $resolved = $this->optionLabel === null
            ? null
            : ($this->optionLabel)($dottedKey, (string) $value, $group);

        if ($resolved !== null) {
            return $resolved;
        }

        $column = new ExportColumn(
            key: $dottedKey,
            faLabel: $dottedKey,
            column: $dottedKey,
            type: $this->hasRule($flat, 'date') ? ExportColumnType::Date : ExportColumnType::Text,
            presentation: match (true) {
                $this->hasRule($flat, 'date') => ValuePresentation::Date,
                $this->hasRule($flat, 'boolean') => ValuePresentation::Boolean,
                default => ValuePresentation::Raw,
            },
        );

        $presenter = new ValuePresenter(null);
        $presented = $presenter->present(
            [$dottedKey => (string) $value],
            [$dottedKey => $column],
            new PresentationOptions(...array_values(self::PRESENTATION)),
        );

        $cell = $presented[$dottedKey] ?? $value;

        return is_scalar($cell) ? (string) $cell : (string) $value;
    }

    /**
     * The form-options group a rule declares, when it carries a live
     * FormOptionValue object (the array rule form sections use).
     */
    private function optionGroup(string|array|null $rule): ?string
    {
        if (! is_array($rule)) {
            return null;
        }

        foreach ($rule as $part) {
            if ($part instanceof FormOptionValue) {
                return $part->group();
            }
        }

        return null;
    }

    private function labelFor(string $sectionKey, string $field): string
    {
        $dotted = "{$sectionKey}.{$field}";

        foreach ($this->labelMaps as $map) {
            if (array_key_exists($dotted, $map) && $map[$dotted] !== '') {
                return (string) $map[$dotted];
            }
        }

        // Repeater leaves ('dependents.*.first_name') also exist in bare form
        // in the attributes map; the leaf fallback keeps those resolving.
        $leaf = (string) substr($dotted, (int) strrpos($dotted, '.') + 1);

        foreach ($this->labelMaps as $map) {
            if (array_key_exists($leaf, $map) && $map[$leaf] !== '') {
                return (string) $map[$leaf];
            }
        }

        return $dotted;
    }

    private function hasRule(string $rule, string $needle): bool
    {
        return preg_match('/(^|\|)'.$needle.'($|\|)/', $rule) === 1;
    }
}
