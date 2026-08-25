<?php

namespace App\Domains\Employee\Sections;

use App\Contracts\Documentable;
use App\Rules\FormOptionValue;
use App\Support\Sections\BaseSection;
use Illuminate\Contracts\Validation\Validator;

/**
 * Document inquiries (استعلام مدارک) — HR-side verification tracking for
 * external inquiries (education degrees, criminal record, social insurance).
 *
 * The section is a fixed-shape map, not a free repeater: education inquiries
 * are keyed by the placement index of the source degree row
 * (`inquiries.education.{index}` mirroring `edu-{index}`), while criminal
 * record and social insurance are single nodes. Fields are expected to evolve
 * (the insurance inquiry format is in flux), so everything is optional and
 * statuses live in the editable `inquiry_status` FormOption group.
 *
 * Document placement: each inquiry node accepts result uploads under
 * `section_key="document_inquiries"` with field keys `inq-edu-{index}`,
 * `inq-criminal-record`, and `inq-social-insurance` (category
 * `inquiry-result`, capped per node). Nothing is required yet — when page
 * counts become mandatory, declare them here and reuse EnforcesRowDocuments.
 */
class DocumentInquiriesSection extends BaseSection
{
    /** Placement pattern for per-degree inquiry results. */
    public const EDU_FIELD_KEY_PATTERN = '/^inq-edu-(\d+)$/';

    /** Placement pattern for the fixed single-node inquiries. */
    public const FIXED_FIELD_KEY_PATTERN = '/^inq-(criminal-record|social-insurance)$/';

    public function key(): string
    {
        return 'document_inquiries';
    }

    public function label(): string
    {
        return __('employee.sections.document_inquiries');
    }

    public function fields(): array
    {
        return [
            'inquiries' => 'array',
            'inquiries.education' => 'array',
            'inquiries.education.*.status' => 'string',
            'inquiries.education.*.note' => 'string',
            'inquiries.criminal_record.status' => 'string',
            'inquiries.criminal_record.note' => 'string',
            'inquiries.social_insurance.status' => 'string',
            'inquiries.social_insurance.note' => 'string',
        ];
    }

    /**
     * Draft save — structural validation (nullable, format only).
     *
     * @return array<string, mixed>
     */
    public function structuralRules(): array
    {
        return [
            'inquiries' => 'nullable|array',

            'inquiries.education' => 'nullable|array',
            'inquiries.education.*.status' => ['nullable', new FormOptionValue('inquiry_status')],
            'inquiries.education.*.note' => 'nullable|string|max:1000',

            'inquiries.criminal_record' => 'nullable|array',
            'inquiries.criminal_record.status' => ['nullable', new FormOptionValue('inquiry_status')],
            'inquiries.criminal_record.note' => 'nullable|string|max:1000',

            'inquiries.social_insurance' => 'nullable|array',
            'inquiries.social_insurance.status' => ['nullable', new FormOptionValue('inquiry_status')],
            'inquiries.social_insurance.note' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Submit — completion validation. The whole section stays optional (HR may
     * submit before inquiries resolve); every provided value must still be
     * structurally valid.
     *
     * @return array<string, mixed>
     */
    public function completionRules(): array
    {
        return $this->structuralRules();
    }

    public function storage(): array
    {
        return [
            'real' => [],
            'jsonb' => 'section_document_inquiries',
        ];
    }

    public function searchMetadata(): array
    {
        return [];
    }

    public function prefill(): array
    {
        return [
            'inquiries' => [],
        ];
    }

    /**
     * Intentionally empty: no category-level requirements are owned here.
     *
     * @return array<string, array<string, mixed>>
     */
    public function documentRequirements(): array
    {
        return [];
    }

    /**
     * Per-node result upload placements. Nothing required yet; caps only.
     * Single source of truth — change counts ONLY here.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function dynamicDocumentRequirements(): array
    {
        return [
            self::EDU_FIELD_KEY_PATTERN => [
                'inquiry-result' => ['required' => false, 'min_files' => 0, 'max_files' => 5],
            ],
            self::FIXED_FIELD_KEY_PATTERN => [
                'inquiry-result' => ['required' => false, 'min_files' => 0, 'max_files' => 5],
            ],
        ];
    }

    /**
     * Human label for an inquiry placement, e.g. «استعلام مدرک تحصیلی 1»,
     * «استعلام عدم سوء پیشینه». Plain digits by convention.
     */
    public function documentFieldKeyLabel(Documentable $entity, string $fieldKey): ?string
    {
        if (preg_match(self::EDU_FIELD_KEY_PATTERN, $fieldKey, $matches) === 1) {
            return __('employee.document_inquiries.field_labels.education_degree', [
                'n' => (int) $matches[1] + 1,
            ]);
        }

        if (preg_match(self::FIXED_FIELD_KEY_PATTERN, $fieldKey, $matches) === 1) {
            return __("employee.document_inquiries.field_labels.{$matches[1]}");
        }

        return null;
    }

    /**
     * ASCII counterpart for file names: `edu-inquiry-1`,
     * `criminal-record-inquiry`, `social-insurance-inquiry`.
     */
    public function documentFieldKeySlug(Documentable $entity, string $fieldKey): ?string
    {
        if (preg_match(self::EDU_FIELD_KEY_PATTERN, $fieldKey, $matches) === 1) {
            return 'edu-inquiry-'.((int) $matches[1] + 1);
        }

        if (preg_match(self::FIXED_FIELD_KEY_PATTERN, $fieldKey, $matches) === 1) {
            return "{$matches[1]}-inquiry";
        }

        return null;
    }

    protected function afterValidation(Validator $validator, array $data, string $mode): void
    {
        $education = $data['inquiries']['education'] ?? null;

        if (! is_array($education)) {
            return;
        }

        foreach (array_keys($education) as $index) {
            if (! is_numeric($index) || (int) $index < 0) {
                $validator->errors()->add(
                    "{$this->key()}.inquiries.education.{$index}",
                    __('employee.document_inquiries.validation.invalid_education_index'),
                );
            }
        }
    }
}
