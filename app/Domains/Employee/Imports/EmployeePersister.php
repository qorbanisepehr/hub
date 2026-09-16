<?php

namespace App\Domains\Employee\Imports;

use App\Domains\Employee\Models\Employee;
use App\Domains\Employee\Services\EmployeeService;
use App\Support\Imports\Contract\RowsPersister;
use App\Support\Imports\Value\ImportOutcome;
use App\Support\Imports\Value\ImportPlan;
use App\Support\Imports\Value\ImportRowError;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The employee persist step of the import pipeline: consumes a dry-run
 * plan and writes its valid rows through the domain's OWN save path
 * (`EmployeeService::create` / `saveSection`) — the persister never
 * touches the model's storage layout directly.
 *
 * Row → sections: a row is a flat `section.field` map; it splits into
 * real-column groups (the section's real fields present in the row) and
 * JSONB leaf groups. JSONB leaves are MERGED over the existing section
 * data, so a row that fills `personal_info.first_name` never wipes the
 * employee's other personal fields (an `update()` on the JSONB column
 * would replace the whole document).
 *
 * Matching (decision #1): a row resolves to an existing employee by
 * personnel code first, then national ID. Unmatched rows create. A row
 * whose anchor collides with a DIFFERENT row in the same file is rejected
 * before any write (same-file conflict); DB-level duplicate personnel
 * codes on create remain the service's uniqueness assert.
 *
 * Chunks: rows are written in transactional chunks (default 200) so a
 * failure mid-file leaves the earlier chunks committed and the report
 * names the exact rows — an import is resumable by re-submitting the
 * fixed file.
 */
final class EmployeePersister implements RowsPersister
{
    public function __construct(
        private readonly EmployeeService $employees,
        private readonly int $chunkSize = 200,
    ) {}

    public function persist(ImportPlan $plan): ImportOutcome
    {
        $conflicts = $this->sameFileConflicts($plan);
        $writable = array_keys(
            array_filter($plan->rows, fn (array $row, int $i) => ! isset($conflicts[$i]), ARRAY_FILTER_USE_BOTH),
        );

        $created = 0;
        $updated = 0;
        $rejected = $conflicts;

        foreach (array_chunk($writable, $this->chunkSize) as $positions) {
            // One transaction per chunk: a failing row rolls back its chunk
            // only; earlier chunks stay committed and the failure lands in
            // the outcome with its file row number. (Nested per-save
            // transactions become savepoints — saveSection stays atomic.)
            DB::transaction(function () use ($plan, $positions, &$created, &$updated, &$rejected): void {
                foreach ($positions as $position) {
                    try {
                        $isNew = $this->writeRow($plan->rows[$position]);
                        $isNew ? $created++ : $updated++;
                    } catch (ValidationException $e) {
                        // saveSection re-validates before its transaction —
                        // a failure here is a reportable row error, not an
                        // abort (belt and suspenders over the dry-run).
                        $rejected[$position] = new ImportRowError(
                            $plan->rowIndexes[$position] ?? $position,
                            $plan->rowNumberAt($position),
                            $e->errors(),
                        );
                    } catch (QueryException $e) {
                        // The database is the final authority on row data:
                        // NOT NULL (23502) and unique (23505) violations are
                        // per-row data errors — reported, not fatal. Any
                        // other DB failure is infrastructure and rethrows.
                        $sqlState = (string) ($e->getPrevious()?->errorInfo[0] ?? $e->getCode());

                        if (! in_array($sqlState, ['23502', '23505'], true)) {
                            throw $e;
                        }

                        $rejected[$position] = new ImportRowError(
                            $plan->rowIndexes[$position] ?? $position,
                            $plan->rowNumberAt($position),
                            ['employment.personnel_code' => ['ثبت ردیف در پایگاه داده نامعتبر بود: '.$e->getMessage()]],
                        );
                    }
                }
            });
        }

        ksort($rejected);

        return new ImportOutcome(
            created: $created,
            updated: $updated,
            processed: $created + $updated + count($rejected),
            rejected: array_values($rejected),
            meta: [
                'definition' => 'employees',
                'file' => $plan->source['file'],
                'format' => $plan->source['format'],
            ],
        );
    }

    /**
     * Write ONE row through the domain's save path. Returns true when a
     * new employee was created, false when an existing one was updated.
     *
     * @param  array<string, string|int|float|bool|null>  $row
     *
     * @throws ValidationException
     */
    private function writeRow(array $row): bool
    {
        [$real, $jsonb] = $this->splitRow($row);
        $existing = $this->matchFor($row);

        if ($existing === null) {
            // Real-column cells are the employees table's own attributes —
            // the same baseData the HTTP create flow posts
            // (StoreEmployeeRequest). Without them the insert violates the
            // schema's NOT NULL columns (personnel_code).
            $this->employees->create($this->flatten($real), $this->sectionsFor([], $jsonb));

            return true;
        }

        // JSONB leaves merge over the entity's CURRENT document — a row
        // that fills one personal field must never wipe the others.
        foreach ($this->sectionsFor($real, $jsonb, $existing) as $sectionKey => $data) {
            $this->employees->saveSection($existing, $sectionKey, $data);
        }

        return false;
    }

    /**
     * Per-section real groups → one flat baseData map (the field names ARE
     * the employees columns; they are unique across sections).
     *
     * @param  array<string, array<string, mixed>>  $real
     * @return array<string, mixed>
     */
    private function flatten(array $real): array
    {
        return array_merge(...array_values($real) ?: [[]]);
    }

    /**
     * The row's existing employee, matched by personnel code first, then
     * national ID (decision #1). Null → create.
     *
     * @param  array<string, string|int|float|bool|null>  $row
     */
    private function matchFor(array $row): ?Employee
    {
        $code = $row['employment.personnel_code'] ?? null;

        if (is_string($code) && $code !== '') {
            $employee = Employee::query()->where('personnel_code', $code)->first();

            if ($employee !== null) {
                return $employee;
            }
        }

        $nationalId = $row['personal_info.id_number'] ?? null;

        if (is_string($nationalId) && $nationalId !== '') {
            return Employee::query()->where('id_number', $nationalId)->first();
        }

        return null;
    }

    /**
     * Split the flat row into per-section real-column and JSONB-leaf
     * groups, then merge JSONB leaves over the existing document so a
     * partial row never erases untouched fields.
     *
     * @param  array<string, string|int|float|bool|null>  $row
     * @return array{0: array<string, array<string, mixed>>, 1: array<string, array<string, mixed>>}
     */
    private function splitRow(array $row): array
    {
        $real = [];
        $jsonb = [];

        foreach ($row as $key => $value) {
            $dot = strpos($key, '.');

            if ($dot === false) {
                continue;
            }

            $sectionKey = substr($key, 0, $dot);
            $field = substr($key, $dot + 1);

            if ($this->isRealField($sectionKey, $field)) {
                $real[$sectionKey][$field] = $value;
            } else {
                // Dotted leaves NEST into the section payload (the shape the
                // forms submit and gatherAllData reads back):
                // `address.city` → ['address' => ['city' => …]].
                Arr::set($jsonb[$sectionKey], $field, $value);
            }
        }

        return [$real, $jsonb];
    }

    /**
     * The section payloads to hand to create()/saveSection(): the real
     * group as-is, and the JSONB group merged over the entity's existing
     * document (null entity → the leaves alone).
     *
     * @param  array<string, array<string, mixed>>  $real
     * @param  array<string, array<string, mixed>>  $jsonb
     * @return array<string, array<string, mixed>>
     */
    private function sectionsFor(array $real, array $jsonb, ?Employee $entity = null): array
    {
        $sections = $real;

        foreach ($jsonb as $sectionKey => $leaves) {
            $existing = [];

            if ($entity !== null) {
                $storage = $this->employees->getSection($sectionKey)->storage();
                $jsonbColumn = $storage['jsonb'] ?? null;

                if ($jsonbColumn !== null) {
                    $existing = $entity->{$jsonbColumn} ?? [];
                }
            }

            // Merge order: the CURRENT document first, then the row's real
            // cells, then the row's JSONB leaves — the row always wins over
            // stored data, stored data always wins over nothing.
            $sections[$sectionKey] = array_merge($existing, $sections[$sectionKey] ?? [], $leaves);
        }

        return $sections;
    }

    /**
     * Whether `section.field` is one of the section's real columns (the
     * section definition's storage is the single source — the persister
     * keeps no field maps).
     */
    private function isRealField(string $sectionKey, string $field): bool
    {
        $storage = $this->employees->getSection($sectionKey)->storage();

        return in_array($field, $storage['real'] ?? [], true);
    }

    /**
     * Rows in one file claiming the same upsert anchor values, rejected
     * before any write — indexed by plan position so outcome errors keep
     * the file's row addressing.
     *
     * @return array<int, ImportRowError>
     */
    private function sameFileConflicts(ImportPlan $plan): array
    {
        $claims = [];
        $rejected = [];

        foreach ($plan->rows as $position => $row) {
            foreach ($this->anchorsOf($row) as $value) {
                if (isset($claims[$value])) {
                    // First occurrence survives; every later claimant of
                    // the same anchor value is rejected up front.
                    $rejected[$position] = new ImportRowError(
                        $plan->rowIndexes[$position] ?? $position,
                        $plan->rowNumberAt($position),
                        [$this->conflictKeyOf($row, $value) => ['این مقدار در ردیف دیگری از همین فایل نیز آمده است.']],
                    );

                    continue;
                }

                $claims[$value] = $position;
            }
        }

        return $rejected;
    }

    /**
     * The anchor values (personnel code, national ID) the row claims.
     *
     * @param  array<string, string|int|float|bool|null>  $row
     * @return list<string>
     */
    private function anchorsOf(array $row): array
    {
        $values = [];

        foreach (['employment.personnel_code', 'personal_info.id_number'] as $key) {
            $value = $row[$key] ?? null;

            if (is_string($value) && $value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * Which anchor key carries the conflicting value (for the error key).
     *
     * @param  array<string, string|int|float|bool|null>  $row
     */
    private function conflictKeyOf(array $row, string $value): string
    {
        return (($row['employment.personnel_code'] ?? null) === $value)
            ? 'employment.personnel_code'
            : 'personal_info.id_number';
    }
}
