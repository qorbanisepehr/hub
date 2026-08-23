<?php

namespace App\Domains\FormOptions\Services;

use App\Domains\FormOptions\Models\FormOption;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FormOptionService
{
    /**
     * Location groups (province → city). They live in the same table but are
     * excluded from aggregate listings and fetched per parent via the
     * parent_value filter. Deeper levels (county, district, …) can be added
     * back by extending the value paths and this list.
     */
    public const LOCATION_GROUPS = [
        'province',
        'city',
    ];

    /**
     * Location hierarchy invariant (v6 §46–47): a child location's parent_value
     * must reference an active option of the mapped parent group. Enforced by
     * the admin Form Requests via {@see parentGroupFor()}.
     */
    private const LOCATION_PARENT_GROUPS = [
        'city' => 'province',
    ];

    private const CACHE_TTL = 3600;

    /**
     * Tables and their section JSON columns that persist form-option values.
     * Scanned before a hard delete so in-use options are never removed.
     */
    private const REFERENCE_COLUMNS = [
        'employees' => [
            'section_personal', 'section_contact_address', 'section_education',
            'section_work_experience', 'section_skills', 'section_training',
            'section_additional_info', 'section_social_insurance',
        ],
        'cvs' => [
            'section_personal', 'section_contact_address', 'section_education',
            'section_work_experience', 'section_skills', 'section_training',
            'section_additional_info',
        ],
        'questionnaires' => [
            'section_personal', 'section_contact_address', 'section_education',
            'section_work_experience', 'section_skills', 'section_training',
            'section_additional_info', 'section_job_request',
        ],
    ];

    private function optionsCacheKey(string $group): string
    {
        return "form_options:{$group}:options";
    }

    public function isLocationGroup(string $group): bool
    {
        return in_array($group, self::LOCATION_GROUPS, true);
    }

    /**
     * The parent group a location group must reference, if any.
     */
    public function parentGroupFor(string $group): ?string
    {
        return self::LOCATION_PARENT_GROUPS[$group] ?? null;
    }

    private function groupsCacheKey(): string
    {
        return 'form_options:groups';
    }

    /**
     * All groups that currently have at least one active option.
     *
     * @return string[]
     */
    public function getGroups(): array
    {
        return Cache::remember($this->groupsCacheKey(), self::CACHE_TTL, function (): array {
            return FormOption::query()
                ->active()
                ->distinct()
                ->orderBy('group')
                ->pluck('group')
                ->values()
                ->all();
        });
    }

    /**
     * Admin: every stored group — location groups included — with its total
     * row count and display label. Unlike {@see getGroups()} this counts
     * inactive options too, because the management table shows them.
     *
     * @return array<int, array{group: string, label: ?string, count: int}>
     */
    public function getAdminGroups(): array
    {
        return FormOption::query()
            ->select('group')
            ->selectRaw('count(*) as options_count')
            ->selectRaw('max(group_label) as group_label')
            ->groupBy('group')
            ->orderBy('group')
            ->get()
            ->map(fn (FormOption $row): array => [
                'group' => $row->group,
                'label' => $row->group_label,
                'count' => (int) $row->options_count,
            ])
            ->all();
    }

    /**
     * Flat, active, ordered options of a group.
     *
     * When a parent value is given, only the options linked to it are returned
     * (used for cascading location selects). When a search term is given, only
     * options whose label contains it are returned, capped by the limit. Both
     * filtered variants are not cached so every parent/child change and search
     * is always reflected.
     *
     * @return array<int, array{value: string, label: string, parent_value: ?string, group_label: ?string}>
     */
    public function getOptions(string $group, ?string $parentValue = null, ?string $search = null, ?int $limit = null): array
    {
        if ($parentValue !== null || $search !== null || $limit !== null) {
            return $this->queryOptions($group, $parentValue, $search, $limit);
        }

        return Cache::remember($this->optionsCacheKey($group), self::CACHE_TTL, fn (): array => $this->queryOptions($group));
    }

    /**
     * @return array<int, array{value: string, label: string, parent_value: ?string, group_label: ?string}>
     */
    private function queryOptions(string $group, ?string $parentValue = null, ?string $search = null, ?int $limit = null): array
    {
        $query = FormOption::query()
            ->ofGroup($group)
            ->active();

        if ($parentValue !== null) {
            $query->where('parent_value', $parentValue);
        }

        if ($search !== null && $search !== '') {
            $query->where('label', 'like', "%{$search}%");
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->ordered()
            ->get(['value', 'label', 'parent_value', 'group_label'])
            ->map(fn (FormOption $option): array => [
                'value' => $option->value,
                'label' => $option->label,
                'parent_value' => $option->parent_value,
                'group_label' => $option->group_label,
            ])
            ->all();
    }

    /**
     * Whether the given value is an active option of the group.
     */
    public function isValid(string $group, string $value): bool
    {
        return FormOption::query()
            ->ofGroup($group)
            ->where('value', $value)
            ->active()
            ->exists();
    }

    /**
     * Whether the combined place value string matches an active city option.
     *
     * Form sections now persist the city option's own value (e.g.
     * «123-1230001001576») as the birth_place / place field. This method looks
     * up the city by its full value and verifies its parent province is active.
     */
    public function isValidCityPlaceSlug(string $combined): bool
    {
        if (! is_string($combined) || $combined === '') {
            return false;
        }

        $cityOption = FormOption::query()
            ->ofGroup('city')
            ->where('value', $combined)
            ->active()
            ->first();

        return $cityOption !== null
            && $this->isValid('province', $cityOption->parent_value);
    }

    /**
     * Whether every given value is an active option of the group.
     *
     * @param  string[]  $values
     */
    public function areValid(string $group, array $values): bool
    {
        if (empty($values)) {
            return true;
        }

        $validCount = FormOption::query()
            ->ofGroup($group)
            ->whereIn('value', $values)
            ->active()
            ->count();

        return $validCount === count($values);
    }

    public function create(array $data): FormOption
    {
        $option = FormOption::create($data);
        $this->flush($option->group);

        return $option->fresh();
    }

    public function update(FormOption $option, array $data): FormOption
    {
        $oldGroup = $option->group;

        $option->update($data);

        $this->flush($oldGroup);
        $this->flush($option->group);

        return $option->fresh();
    }

    public function delete(FormOption $option): void
    {
        $group = $option->group;
        $option->delete();

        $this->flush($group);
    }

    /**
     * Whether any employee, CV, or questionnaire section JSON stores this
     * option's value (soft-deleted rows included — they can be restored).
     *
     * Matches the value as a quoted JSON token anywhere inside the section
     * documents, using only portable SQL so it runs on every supported
     * engine. Slight over-matching (e.g. a key named exactly like the value)
     * errs on the safe side: the option is kept.
     */
    public function isReferenced(FormOption $option): bool
    {
        $needle = str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            '"'.$option->value.'"',
        );

        foreach (self::REFERENCE_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $exists = DB::table($table)
                    ->whereRaw("cast({$column} as text) like ? escape '\\'", ["%{$needle}%"])
                    ->exists();

                if ($exists) {
                    return true;
                }
            }
        }

        return false;
    }

    public function toggleActive(FormOption $option): FormOption
    {
        $option->update(['is_active' => ! $option->is_active]);

        $this->flush($option->group);

        return $option->fresh();
    }

    public function flush(string $group): void
    {
        Cache::forget($this->optionsCacheKey($group));
        Cache::forget($this->groupsCacheKey());
    }
}
