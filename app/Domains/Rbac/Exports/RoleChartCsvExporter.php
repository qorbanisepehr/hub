<?php

namespace App\Domains\Rbac\Exports;

use App\Domains\Rbac\Models\Role;
use Illuminate\Support\Collection;

class RoleChartCsvExporter
{
    public const FIELDS = [
        'system_name' => ['label' => 'نام سیستمی', 'column' => 'System Name'],
        'description' => ['label' => 'توضیحات', 'column' => 'Description'],
        'is_active' => ['label' => 'وضعیت', 'column' => 'Active'],
        'user_name' => ['label' => 'نام', 'column' => 'User Name'],
        'user_last_name' => ['label' => 'نام خوانوادگی', 'column' => 'User Last Name'],
        'user_personnel_code' => ['label' => 'کدپرسنلی', 'column' => 'Personnel Code'],
        'user_count' => ['label' => 'تعداد کاربران', 'column' => 'User Count'],
        'children_count' => ['label' => 'تعداد زیرمجموعه مستقیم', 'column' => 'Direct Subordinates'],
        'min_education' => ['label' => 'حداقل تحصیلات', 'column' => 'Min Education'],
        'min_experience_years' => ['label' => 'حداقل سابقه (سال)', 'column' => 'Min Experience (Years)'],
        'matrix_managers' => ['label' => 'مدیران ماتریسی', 'column' => 'Matrix Managers'],
    ];

    /** @return array<int, array{key: string, label: string, column: string}> */
    public function availableFields(): array
    {
        return collect(self::FIELDS)
            ->map(fn (array $field, string $key) => [
                'key' => $key,
                'label' => $field['label'],
                'column' => $field['column'],
            ])
            ->values()
            ->all();
    }

    /**
     * خروجی CSV سازگار با Visio Organization Chart Wizard.
     *
     * @param  int|null  $rootId  ریشه زیرمجموعه؛ null یعنی کل چارت.
     * @param  array<int, string>  $fields  کلید فیلدهای اضافی.
     */
    public function export(?int $rootId, array $fields): string
    {
        $fields = array_values(array_intersect($fields, array_keys(self::FIELDS)));

        $allRoles = Role::query()->withCount('users')->with('users.employee')->get();
        $rolesById = $allRoles->keyBy('id');

        $roles = $this->collectSubtree($allRoles, $rootId);
        $names = $this->uniqueNames($roles);

        $childrenCounts = [];
        foreach ($roles as $role) {
            if ($role->parent_id !== null) {
                $childrenCounts[$role->parent_id] = ($childrenCounts[$role->parent_id] ?? 0) + 1;
            }
        }

        $header = array_merge(['Name', 'Manager'], $this->columnsFor($fields));
        $rows = [$header];

        foreach ($roles as $role) {
            $parentName = ($role->parent_id !== null && isset($names[$role->parent_id]))
                ? $names[$role->parent_id]
                : '';

            $row = [$names[$role->id], $parentName];
            foreach ($fields as $field) {
                $row[] = $this->fieldValue($role, $field, $rolesById, $childrenCounts);
            }
            $rows[] = $row;
        }

        return $this->toCsv($rows);
    }

    /** @return Collection<int, Role> */
    private function collectSubtree(Collection $roles, ?int $rootId): Collection
    {
        if ($rootId === null) {
            return $roles->values();
        }

        $childrenByParent = [];
        foreach ($roles as $role) {
            if ($role->parent_id !== null) {
                $childrenByParent[$role->parent_id][] = $role;
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

    /** @return array<int, string> */
    private function columnsFor(array $fields): array
    {
        return array_map(fn (string $field) => self::FIELDS[$field]['column'], $fields);
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
        // dd($requirements, $role,
        //     $userName,
        //     $userLastName,
        //     $userPersonnelCode,
        // );

        return match ($field) {
            'system_name' => $role->name,
            'description' => (string) ($role->description ?? ''),
            'user_name' => $userName,
            'user_last_name' => $userLastName,
            'user_personnel_code' => $userPersonnelCode,
            'is_active' => $role->is_active ? 'Yes' : 'No',
            'user_count' => (string) ($role->users_count ?? 0),
            'children_count' => (string) ($childrenCounts[$role->id] ?? 0),
            'min_education' => (string) ($requirements['min_education'] ?? ''),
            'min_experience_years' => isset($requirements['min_experience_years'])
                ? (string) $requirements['min_experience_years']
                : '',
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

    /**
     * ساخت خروجی CSV خام بدون BOM.
     * هر ردیف یک خط جداگانه با \r\n واقعی.
     *
     * @param  array<int, array<int, string>>  $rows
     */
    private function toCsv(array $rows): string
    {
        $lines = [];

        foreach ($rows as $row) {
            $escaped = array_map(
                fn ($field) => $this->escapeCsvField((string) $field),
                $row,
            );
            $lines[] = implode(',', $escaped);
        }

        // هر ردیف یک خط جداگانه با \r\n واقعی (نه متنی)
        return implode("\r\n", $lines)."\r\n";
    }

    /**
     * Escape فیلد CSV فقط در صورت نیاز.
     * فقط فیلدهای حاوی کاما، دابل‌کوت یا line-break quote می‌شوند.
     */
    private function escapeCsvField(string $field): string
    {
        if (preg_match('/[",\r\n]/', $field)) {
            return '"'.str_replace('"', '""', $field).'"';
        }

        return $field;
    }
}
