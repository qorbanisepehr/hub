<?php

namespace App\Domains\Authorization\Exports;

use App\Domains\Authorization\Models\Role;
use App\Support\Exports\Contract\TabularExporter;
use App\Support\Exports\Value\ExportColumn;
use App\Support\Exports\Value\ExportColumnType;
use App\Support\Exports\Value\ExportOptions;
use App\Support\Exports\Value\ExportRequest;
use Illuminate\Support\Collection;

/**
 * Role chart export for the Visio Organization Chart Wizard.
 *
 * Migrated from RoleChartCsvExporter: the data assembly (subtree, unique
 * names, field values) stayed here; the CSV bytes moved to the kernel's
 * CsvWriter. The Visio quirks are byte/encoding concerns of the *target*
 * and are applied by the controller via ExportOptions (no BOM, CRLF is the
 * writer's default).
 */
final class RoleChartExporter implements TabularExporter
{
    /**
     * User-selectable fields (the export-fields picker). Name/Manager are
     * structural and always present; they are not part of this catalog.
     */
    private const FIELDS = [
        'system_name' => ['label' => 'نام سیستمی', 'column' => 'System Name'],
        'description' => ['label' => 'توضیحات', 'column' => 'Description'],
        'is_active' => ['label' => 'وضعیت', 'column' => 'Active'],
        'type' => ['label' => 'نوع نقش', 'column' => 'Role Type'],
        'user_name' => ['label' => 'نام', 'column' => 'User Name'],
        'user_last_name' => ['label' => 'نام خوانوادگی', 'column' => 'User Last Name'],
        'user_personnel_code' => ['label' => 'کدپرسنلی', 'column' => 'Personnel Code'],
        'user_count' => ['label' => 'تعداد کاربران', 'column' => 'User Count'],
        'children_count' => ['label' => 'تعداد زیرمجموعه مستقیم', 'column' => 'Direct Subordinates'],
        'min_education' => ['label' => 'حداقل تحصیلات', 'column' => 'Min Education'],
        'min_related_experience_years' => ['label' => 'حداقل سابقه مرتبط (سال)', 'column' => 'Min Related Experience (Years)'],
        'min_unrelated_experience_years' => ['label' => 'حداقل سابقه غیرمرتبط (سال)', 'column' => 'Min Unrelated Experience (Years)'],
        'fields_of_study' => ['label' => 'رشته تحصیلی', 'column' => 'Fields of Study'],
        'matrix_managers' => ['label' => 'مدیران ماتریسی', 'column' => 'Matrix Managers'],
    ];

    public function __construct(
        /** @var Collection<int, Role> Roles already scoped by the controller (authorization happens there). */
        private readonly Collection $scopedRoles,
        private readonly ?int $rootId = null,
    ) {}

    public static function visioOptions(): ExportOptions
    {
        return new ExportOptions(bom: false, formulaGuard: false);
    }

    public function columns(): array
    {
        return array_map(
            fn (string $key) => new ExportColumn(
                key: $key,
                faLabel: self::FIELDS[$key]['label'],
                column: self::FIELDS[$key]['column'],
                type: $key === 'user_count' || $key === 'children_count'
                    ? ExportColumnType::Number
                    : ExportColumnType::Text,
            ),
            array_keys(self::FIELDS),
        );
    }

    public function columnsFor(ExportRequest $request): array
    {
        $structural = [
            new ExportColumn('name', 'نام', 'Name'),
            new ExportColumn('manager', 'مدیر', 'Manager'),
        ];

        // Visio contract: empty selection = structural columns only.
        if ($request->fields === []) {
            return $structural;
        }

        $catalog = $this->columns();
        $byKey = collect($catalog)->keyBy(fn (ExportColumn $column) => $column->key);

        return array_merge($structural, array_values(array_filter(
            array_map(fn (string $key) => $byKey->get($key), $request->fields),
        )));
    }

    /**
     * @return iterable<array<string, string|int|float|bool|null>>
     */
    public function rows(ExportRequest $request): iterable
    {
        $fields = array_values(array_intersect($request->fields, array_keys(self::FIELDS)));

        $rolesById = $this->scopedRoles->keyBy('id');

        $parentByRole = [];
        foreach ($this->scopedRoles as $role) {
            $parentByRole[$role->id] = $role->parent_id;
        }

        $roles = $this->collectSubtree($this->scopedRoles, $this->rootId, $parentByRole);
        $names = $this->uniqueNames($roles);

        $childrenCounts = [];
        foreach ($roles as $role) {
            $parentId = $parentByRole[$role->id] ?? null;
            if ($parentId !== null) {
                $childrenCounts[$parentId] = ($childrenCounts[$parentId] ?? 0) + 1;
            }
        }

        foreach ($roles as $role) {
            $parentId = $parentByRole[$role->id] ?? null;
            $parentName = ($parentId !== null && isset($names[$parentId]))
                ? $names[$parentId]
                : '';

            $row = [
                'name' => $names[$role->id],
                'manager' => $parentName,
            ];
            foreach ($fields as $field) {
                $row[$field] = $this->fieldValue($role, $field, $rolesById, $childrenCounts);
            }
            yield $row;
        }
    }

    public function baseFilename(): string
    {
        return 'org-chart-roles';
    }

    /**
     * @param  array<int, int>  $parentByRole
     * @return Collection<int, Role>
     */
    private function collectSubtree(Collection $roles, ?int $rootId, array $parentByRole): Collection
    {
        if ($rootId === null) {
            return $roles->values();
        }

        $childrenByParent = [];
        foreach ($roles as $role) {
            $parentId = $parentByRole[$role->id] ?? null;
            if ($parentId !== null) {
                $childrenByParent[$parentId][] = $role;
            }
        }

        $result = new Collection;
        $queue = [$rootId];
        $seen = [];

        while ($queue !== []) {
            $id = array_shift($queue);
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;

            $role = $roles->firstWhere('id', $id);
            if ($role === null) {
                continue;
            }

            $result->push($role);
            foreach ($childrenByParent[$id] ?? [] as $child) {
                $queue[] = $child->id;
            }
        }

        return $result->values();
    }

    /**
     * یکتا کردن نام‌ها برای Visio.
     *
     * @return array<int, string> نگاشت role_id => نام یکتا
     */
    private function uniqueNames(Collection $roles): array
    {
        $names = [];
        $used = [];

        foreach ($roles as $role) {
            $candidate = $role->display_name;

            if (isset($used[$candidate])) {
                $candidate = $role->display_name.' ('.$role->id.')';
                $suffix = 2;
                while (isset($used[$candidate])) {
                    $candidate = $role->display_name.' ('.$role->id.'-'.$suffix.')';
                    $suffix++;
                }
            }

            $used[$candidate] = true;
            $names[$role->id] = $candidate;
        }

        return $names;
    }

    /** @param  array<int, int>  $childrenCounts */
    private function fieldValue(Role $role, string $field, Collection $rolesById, array $childrenCounts): string
    {
        $requirements = $role->requirements;
        if (is_string($requirements)) {
            $requirements = json_decode($requirements, true) ?? [];
        }
        $requirements = $requirements ?? [];
        $userName = $role?->users[0]?->employee?->first_name ?? $role?->users[0]?->name ?? '';
        $userLastName = $role?->users[0]?->employee?->last_name ?? '';
        $userPersonnelCode = $role?->users[0]?->employee?->personnel_code ?? '';

        return match ($field) {
            'system_name' => $role->name,
            'description' => (string) ($role->description ?? ''),
            'user_name' => $userName,
            'user_last_name' => $userLastName,
            'user_personnel_code' => $userPersonnelCode,
            'is_active' => $role->is_active ? 'Yes' : 'No',
            'type' => Role::TYPES[$role->type] ?? (string) $role->type,
            'user_count' => (string) ($role->users_count ?? 0),
            'children_count' => (string) ($childrenCounts[$role->id] ?? 0),
            'min_education' => (string) ($requirements['min_education'] ?? ''),
            'min_related_experience_years' => isset($requirements['min_related_experience_years'])
                ? (string) $requirements['min_related_experience_years']
                : '',
            'min_unrelated_experience_years' => isset($requirements['min_unrelated_experience_years'])
                ? (string) $requirements['min_unrelated_experience_years']
                : '',
            'fields_of_study' => implode(', ', $requirements['fields_of_study'] ?? []),
            'matrix_managers' => collect($role->matrix_managers ?? [])
                ->map(function ($manager) use ($rolesById) {
                    $manager = (array) $manager;

                    return $rolesById->get($manager['role_id'] ?? null)?->display_name;
                })
                ->filter()
                ->join(', '),
            default => '',
        };
    }
}
