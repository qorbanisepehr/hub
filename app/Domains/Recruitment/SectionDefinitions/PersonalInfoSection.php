<?php

namespace App\Domains\Recruitment\SectionDefinitions;

use App\Rules\NationalIdRule;
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
        return __('recruitment.sections.personal_info');
    }

    public function documentRequirements(): array
    {
        return [
            'national-card' => [
                'required' => true,
                'max_files' => 1,
                'record_keys' => ['front', 'back'],
            ],
            'birth-certificate' => [
                'required' => true,
                'max_files' => 1,
                'record_keys' => ['page-1', 'page-2', 'page-3'],
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
            'marital_status' => 'string',
            'first_name_en' => 'string',
            'last_name_en' => 'string',
            'dependents_count' => 'integer',
            'children_count' => 'integer',
            'spouse_employment_status' => 'string',
            'spouse_job' => 'string',
            'military_status' => 'object',
            'national_id' => 'string',
        ];
    }

    public function structuralRules(): array
    {
        return [
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'gender' => 'nullable|in:male,female',
            'blood_group' => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'birth_date' => 'nullable|date',
            'birth_place' => 'nullable|string|max:100',
            'birth_certificate_number' => 'nullable|string|max:20|regex:/^\d+$/',
            'father_name' => 'nullable|string|max:100',
            'religion' => 'nullable|string|max:50',
            'marital_status' => 'nullable|in:single,married',
            'first_name_en' => 'nullable|string|max:100',
            'last_name_en' => 'nullable|string|max:100',
            'dependents_count' => 'nullable|integer|min:0',
            'children_count' => 'nullable|integer|min:0',
            'spouse_employment_status' => 'nullable|in:employed,housewife',
            'spouse_job' => 'nullable|string|max:100',
            'military_status' => 'nullable|array',
            'military_status.status' => 'nullable|in:completed,amrieh,guardian_exemption,medical_exemption,education_exemption,leader_pardon,service_purchase,other',
            'military_status.organization' => 'nullable|string|max:100',
            'military_status.from' => 'nullable|string',
            'military_status.to' => 'nullable|string',
            'military_status.reason' => 'nullable|string|max:255',
            'national_id' => ['nullable', 'string', new NationalIdRule],
        ];
    }

    public function completionRules(): array
    {
        return [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'gender' => 'required|in:male,female',
            'blood_group' => 'required|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'birth_date' => 'required|date|before:today',
            'birth_place' => 'required|string|max:100',
            'birth_certificate_number' => 'required|string|max:20|regex:/^\d+$/',
            'father_name' => 'required|string|max:100',
            'religion' => 'required|string|max:50',
            'marital_status' => 'required|in:single,married',
            'national_id' => ['required', 'string', new NationalIdRule],
            'military_status' => 'required_unless:gender,female',
            'military_status.status' => 'required_with:military_status',
            'military_status.organization' => 'required_with:military_status',
            'military_status.from' => 'required_with:military_status',
            'military_status.to' => 'required_with:military_status',
            'military_status.reason' => 'required_with:military_status',
            'spouse_employment_status' => 'required_if:marital_status,married',
            'spouse_job' => 'required_if:spouse_employment_status,employed',
        ];
    }

    public function storage(): array
    {
        return [
            'real' => ['first_name', 'last_name', 'national_id', 'gender', 'birth_date', 'marital_status'],
            'jsonb' => 'section_personal',
        ];
    }

    public function searchMetadata(): array
    {
        return ['national_id', 'birth_certificate_number'];
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
