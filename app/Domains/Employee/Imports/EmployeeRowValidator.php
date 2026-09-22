<?php

namespace App\Domains\Employee\Imports;

use App\Domains\Employee\Services\EmployeeService;
use App\Support\Imports\Contract\RowValidator;
use App\Support\Sections\SectionDefinition;

/**
 * Validates one mapped import row against the section definitions' own
 * structural rules — the same `validateData(MODE_STRUCTURAL)` the draft
 * save path uses, never a hand-rewritten rule set. The row arrives flat
 * (`section.field` keys); it is grouped into section payloads, each
 * section validates its slice, and errors are mapped back to the column
 * keys the file addresses.
 *
 * Import-specific guards live here too (they are row-level facts no
 * section knows about): at least one upsert anchor must be filled, and
 * anchor values must be well-formed.
 */
final class EmployeeRowValidator implements RowValidator
{
    /**
     * The column keys a row may not leave entirely empty (upsert anchors —
     * decision #1: either code or national ID addresses the employee).
     *
     * @var list<string>
     */
    private const ANCHOR_KEYS = ['employment.personnel_code', 'personal_info.id_number'];

    public function __construct(
        private readonly EmployeeService $sections,
    ) {}

    public function validate(array $row): array
    {
        $errors = [];

        $filledAnchors = array_filter(
            self::ANCHOR_KEYS,
            fn (string $key) => array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '',
        );

        if ($filledAnchors === []) {
            $errors['employment.personnel_code'] = ['حداقل یکی از کد پرسنلی یا کد ملی باید پر باشد.'];
        }

        foreach ($this->groupIntoSections($row) as $sectionKey => $data) {
            $section = $this->sections->getSection($sectionKey);
            $validator = $section->validateData($data, SectionDefinition::MODE_STRUCTURAL);

            if (! $validator->fails()) {
                continue;
            }

            foreach ($validator->errors()->toArray() as $field => $messages) {
                // validateData() already prefixes error keys with the
                // section key (`personal_info.id_number`, dotted rules
                // `personal_info.military_status.status`) — exactly the
                // file's column-key shape, so they pass through as-is.
                $errors[$field] = $messages;
            }
        }

        return $errors === []
            ? ['ok' => true, 'row' => $row]
            : ['ok' => false, 'errors' => $errors];
    }

    /**
     * Group the flat row into section payloads (`section.field` →
     * `[section => [field => value]]`), skipping sections with no cells.
     * Field parts after the first dot belong to nested rule keys and are
     * rejoined verbatim — structural rules are dotted natively.
     *
     * @param  array<string, string|int|float|bool|null>  $row
     * @return array<string, array<string, mixed>>
     */
    private function groupIntoSections(array $row): array
    {
        $grouped = [];

        foreach ($row as $key => $value) {
            $dot = strpos($key, '.');

            if ($dot === false) {
                continue;
            }

            $sectionKey = substr($key, 0, $dot);
            $field = substr($key, $dot + 1);

            $grouped[$sectionKey][$field] = $value;
        }

        return $grouped;
    }
}
