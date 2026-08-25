<?php

namespace App\Domains\Questionnaire\Sections;

use App\Contracts\Documentable;
use App\Rules\FormOptionValue;
use App\Rules\IdNumberRule;
use App\Support\Sections\BaseSection;
use App\Support\ValidationRules;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;

class PersonalInfoSection extends BaseSection
{
    public function key(): string
    {
        return 'personal_info';
    }

    public function label(): string
    {
        return __('questionnaire.sections.personal_info');
    }

    public function documentRequirements(): array
    {
        return [
            'national-card' => [
                'required' => true,
                'max_files' => 1,
                'field_keys' => ['front', 'back'],
            ],
            'birth-certificate' => [
                'required' => true,
                'max_files' => 1,
                'field_keys' => ['page-1', 'page-2', 'page-3'],
            ],
            'personnel-photo' => [
                'required' => true,
                'max_files' => 1,
                'min_file_size' => 20 * 1024,        // 20KB
                'max_file_size' => 500 * 1024,       // 500KB
                'mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
                'dimensions' => [
                    'min_width' => 300,
                    'min_height' => 400,
                    'max_width' => 2000,
                    'max_height' => 3000,
                    'aspect_ratio' => null,          // e.g., 3/4 for portrait
                ],
            ],
        ];
    }

    /**
     * Labels for the static field keys declared in documentRequirements()
     * (national-card front/back, birth-certificate pages).
     */
    public function documentFieldKeyLabel(Documentable $entity, string $fieldKey): ?string
    {
        return [
            'front' => __('questionnaire.documents.fields.front'),
            'back' => __('questionnaire.documents.fields.back'),
            'page-1' => __('questionnaire.documents.fields.page_1'),
            'page-2' => __('questionnaire.documents.fields.page_2'),
            'page-3' => __('questionnaire.documents.fields.page_3'),
        ][$fieldKey] ?? null;
    }

    /**
     * ASCII counterpart of documentFieldKeyLabel() for the same static keys.
     */
    public function documentFieldKeySlug(Documentable $entity, string $fieldKey): ?string
    {
        return [
            'front' => 'front',
            'back' => 'back',
            'page-1' => 'page-1',
            'page-2' => 'page-2',
            'page-3' => 'page-3',
        ][$fieldKey] ?? null;
    }

    public function fields(): array
    {
        return [
            'gender' => 'string',
            'blood_group' => 'string',
            'birth_date' => 'string',
            'birth_place' => 'string',
            'birth_certificate_number' => 'string',
            'father_name' => 'string',
            'religion' => 'string',
            'religion_sect' => 'string',
            'marital_status' => 'string',
            'first_name_en' => 'string',
            'last_name_en' => 'string',
            'dependents_count' => 'integer',
            'children_count' => 'integer',
            'spouse_employment_status' => 'string',
            'spouse_job' => 'string',
            'military_status' => 'object',
            'id_number' => 'string',
        ];
    }

    public function structuralRules(): array
    {
        return [
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'gender' => ['nullable', new FormOptionValue('gender')],
            'blood_group' => ['nullable', new FormOptionValue('blood_group')],
            'birth_date' => 'nullable|'.ValidationRules::DATE,
            'birth_place' => ['nullable', new FormOptionValue('city', 'province')],
            'birth_certificate_number' => 'nullable|'.ValidationRules::DIGITS_ONLY,
            'father_name' => 'nullable|string|max:100',
            'religion' => ['nullable', new FormOptionValue('religion')],
            'religion_sect' => ['nullable', new FormOptionValue('religion_sect')],
            'marital_status' => ['nullable', new FormOptionValue('marital_status')],
            'first_name_en' => 'nullable|string|max:100',
            'last_name_en' => 'nullable|string|max:100',
            'dependents_count' => 'nullable|integer|min:0',
            'children_count' => 'nullable|integer|min:0',
            'spouse_employment_status' => ['nullable', new FormOptionValue('spouse_employment_status')],
            'spouse_job' => 'nullable|string|max:100',
            'military_status' => 'nullable|array',
            'military_status.status' => ['nullable', new FormOptionValue('military_status')],
            'military_status.organization' => 'nullable|string|max:100',
            'military_status.from' => 'nullable|string',
            'military_status.to' => 'nullable|string',
            'military_status.reason' => 'nullable|string|max:255',
            'id_number' => ['nullable', 'string', new IdNumberRule],
        ];
    }

    public function completionRules(): array
    {
        return [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'gender' => ['required', new FormOptionValue('gender')],
            'blood_group' => ['required', new FormOptionValue('blood_group')],
            'birth_date' => 'required|'.ValidationRules::DATE.'|before:today',
            'birth_place' => ['required', new FormOptionValue('city', 'province')],
            'birth_certificate_number' => 'required|'.ValidationRules::DIGITS_ONLY,
            'father_name' => 'required|string|max:100',
            'religion' => ['required', new FormOptionValue('religion')],
            'religion_sect' => ['nullable', new FormOptionValue('religion_sect')],
            'marital_status' => ['required', new FormOptionValue('marital_status')],
            'id_number' => ['required', 'string', new IdNumberRule],
            'military_status' => 'required_unless:gender,female',
            'military_status.status' => ['required_with:military_status', new FormOptionValue('military_status')],
            'military_status.organization' => 'required_with:military_status',
            'military_status.from' => 'required_if:military_status.status,amrieh,guardian_exemption,medical_exemption,education_exemption,leader_pardon|nullable|string',
            'military_status.to' => 'required_with:military_status',
            'military_status.reason' => 'required_if:military_status.status,other|nullable|string|max:255',
            'spouse_employment_status' => 'required_if:marital_status,married',
            'spouse_job' => 'required_if:spouse_employment_status,employed',
        ];
    }

    public function storage(): array
    {
        return [
            'real' => ['first_name', 'last_name', 'id_number', 'gender', 'birth_date', 'marital_status'],
            'jsonb' => 'section_personal',
        ];
    }

    public function searchMetadata(): array
    {
        return ['id_number', 'birth_certificate_number'];
    }

    public function prefill(): array
    {
        return [];
    }

    protected function afterValidation(Validator $validator, array $data, string $mode): void
    {
        $birthDate = $data['birth_date'] ?? null;
        if ($mode === self::MODE_COMPLETION && $birthDate && Carbon::parse($birthDate)->age < 18) {
            $validator->errors()->add(
                "{$this->key()}.birth_date",
                __('messages.validation.min_age'),
            );
        }

        $militaryFrom = $data['military_status']['from'] ?? null;
        $militaryTo = $data['military_status']['to'] ?? null;
        if ($militaryFrom && $militaryTo && $militaryTo < $militaryFrom) {
            $validator->errors()->add(
                "{$this->key()}.military_status.to",
                __('messages.validation.work_date_order'),
            );
        }
    }
}
