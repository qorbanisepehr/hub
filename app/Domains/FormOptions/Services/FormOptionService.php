<?php

namespace App\Domains\FormOptions\Services;

use App\Domains\FormOptions\Models\FormOption;
use Illuminate\Support\Facades\Cache;

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

    private const CACHE_TTL = 3600;

    private function optionsCacheKey(string $group): string
    {
        return "form_options:{$group}:options";
    }

    public function isLocationGroup(string $group): bool
    {
        return in_array($group, self::LOCATION_GROUPS, true);
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
        if ($parentValue !== null || $search !== null) {
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
     * Whether the given label is an active option of the group.
     *
     * Form sections persist the readable label (e.g. «تهران») instead of the
     * stable value key, so saving is validated against the label column.
     */
    public function isValidLabel(string $group, string $label): bool
    {
        return FormOption::query()
            ->ofGroup($group)
            ->where('label', $label)
            ->active()
            ->exists();
    }

    /**
     * Resolve a group label back to its stable value key (used to re-derive
     * parent/child links such as province -> city when only labels are stored).
     */
    public function labelToValue(string $group, string $label): ?string
    {
        return FormOption::query()
            ->ofGroup($group)
            ->where('label', $label)
            ->active()
            ->value('value');
    }

    /**
     * Whether the combined place string matches an active city option under the
     * matching province option, e.g. «تهران-تهران» = province label «تهران» +
     * city label «تهران». Splits on the first `-` only.
     */
    public function isValidCityPlace(string $combined): bool
    {
        if (! is_string($combined) || $combined === '') {
            return false;
        }

        [$provinceLabel, $cityLabel] = array_pad(explode('-', $combined, 2), 2, null);

        if ($provinceLabel === null || $cityLabel === null || $cityLabel === '') {
            return false;
        }

        $provinceValue = $this->labelToValue('province', $provinceLabel);

        return $provinceValue !== null
            && FormOption::query()
                ->ofGroup('city')
                ->where('label', $cityLabel)
                ->where('parent_value', $provinceValue)
                ->active()
                ->exists();
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
