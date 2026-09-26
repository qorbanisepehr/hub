<?php

namespace App\Domains\Employee\Exports;

use App\Domains\Authorization\Services\FieldAccess;
use App\Domains\Employee\Models\Employee;
use App\Domains\Employee\Services\EmployeeService;
use App\Domains\FormOptions\Services\FormOptionService;
use App\Support\Exports\Contract\DocumentSource;
use App\Support\Exports\Documents\SectionDocumentBuilder;
use App\Support\Exports\Value\Document\DocumentField;
use App\Support\Exports\Value\Document\DocumentSpec;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The printable employee-profile document (PDF/Word). Section walk, labels
 * and presentation come from the shared SectionDocumentBuilder — the same
 * single-source-of-truth the tabular export uses (validation.attributes
 * first, dotted keys machine-stable).
 *
 * Field access is honored through the SAME deny-based groups the API
 * resource strips: the profile document must never print what the actor
 * cannot see. The probe runs each config group's response keys through
 * FieldAccess::filter; a group whose keys all come back stripped is denied,
 * and the sections it covers are dropped from the document (see
 * GROUP_SECTIONS). When the personal-info group is denied the printed name
 * disappears with it.
 */
final class EmployeeProfileDocument implements DocumentSource
{
    /**
     * authorization-fields group key  profile sections it covers.
     * personal_info's group fields include both section_personal and
     * section_dependents, so both sections share its fate.
     *
     * @var array<string, list<string>>
     */
    private const GROUP_SECTIONS = [
        'personal_info' => ['personal_info', 'dependents'],
        'employment_info' => ['employment'],
        'document_inquiries' => ['document_inquiries'],
    ];

    public function __construct(
        private readonly EmployeeService $sections,
        private readonly FieldAccess $fieldAccess,
        private readonly FormOptionService $formOptions,
        private readonly ?Authenticatable $actor,
    ) {}

    public function document(mixed $entity): DocumentSpec
    {
        /** @var Employee $entity */
        $visible = $this->visibleSections($entity);
        $personalAllowed = in_array('personal_info', $visible, true);
        $employmentAllowed = in_array('employment', $visible, true);

        $builder = new SectionDocumentBuilder(
            sections: $this->sections,
            labelMaps: [
                (array) trans('validation.attributes'),
                (array) trans('employee.exports.fields'),
            ],
            optionLabel: fn (string $key, string $value, ?string $group): ?string => $this->optionLabel($key, $value, $group),
            onlySections: $visible,
        );

        $meta = array_values(array_filter([
            $employmentAllowed
                ? DocumentField::from(
                    (string) $this->attributeLabel('employment.personnel_code'),
                    $entity->personnel_code === null ? null : (string) $entity->personnel_code,
                )
                : null,
            DocumentField::from((string) __('exports.generated_at'), now()->format('Y/m/d H:i')),
        ]));

        return new DocumentSpec(
            title: (string) __('employee.exports.document.title'),
            sections: $builder->build($entity),
            meta: $meta,
            subtitle: $personalAllowed
                ? trim("{$entity->first_name} {$entity->last_name}")
                : '',
        );
    }

    public function baseFilename(): string
    {
        return 'employee-profile';
    }

    /**
     * The sections the actor may see: registry order minus every section
     * covered by an explicitly denied field group. The probe mirrors the
     * resource's stripping — filter the group's marker keys and check for
     * loss — so a role WITHOUT any deny rule keeps the full document.
     *
     * @return list<string>
     */
    private function visibleSections(Employee $entity): array
    {
        $denied = [];

        if ($this->actor !== null) {
            $groups = (array) config('authorization-fields.groups.employee', []);

            foreach ($groups as $groupKey => $definition) {
                $fields = (array) ($definition['fields'] ?? []);

                if ($fields === [] || ! isset(self::GROUP_SECTIONS[$groupKey])) {
                    continue;
                }

                $kept = $this->fieldAccess->filter(
                    $this->actor,
                    'employee',
                    $entity,
                    array_fill_keys($fields, 1),
                );

                if ($kept === []) {
                    $denied = [...$denied, ...self::GROUP_SECTIONS[$groupKey]];
                }
            }
        }

        return array_values(array_filter(
            array_keys($this->sections->sections()),
            static fn (string $key): bool => ! in_array($key, $denied, true),
        ));
    }

    /**
     * Option-valued cells: form-options groups resolve through the options
     * table (their single source); the fixed in-list enums through the
     * employee exports vocabulary map — exactly the tabular exporter's
     * resolution chain, keyed by the same dotted paths.
     */
    private function optionLabel(string $dottedKey, string $value, ?string $group): ?string
    {
        if ($group === null) {
            $maps = trans('employee.exports.options');

            if (is_array($maps) && is_array($maps[$dottedKey] ?? null)) {
                return $maps[$dottedKey][$value] ?? null;
            }

            return null;
        }

        $resolved = $this->formOptions->resolveValues($group, [$value]);

        return $resolved[0]['label'] ?? null;
    }

    private function attributeLabel(string $dottedKey): ?string
    {
        $map = trans('validation.attributes');

        return is_array($map) && isset($map[$dottedKey]) ? (string) $map[$dottedKey] : null;
    }
}
