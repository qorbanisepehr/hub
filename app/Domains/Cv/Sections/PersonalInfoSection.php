<?php

namespace App\Domains\Cv\Sections;

use App\Rules\FormOptionValue;
use App\Rules\IdNumberRule;
use App\Support\Sections\Definitions\PersonalInfoSection as BasePersonalInfoSection;
use App\Support\ValidationRules;

class PersonalInfoSection extends BasePersonalInfoSection
{
    public function __construct()
    {
        parent::__construct(labelKey: 'cv.sections.personal_info');
    }

    public function documentRequirements(): array
    {
        // A CV declares its document requirements on other sections; the
        // personal-info step carries none.
        return [];
    }

    public function fields(): array
    {
        return [
            'first_name' => 'string',
            'last_name' => 'string',
            'gender' => 'string',
            'birth_date' => 'string',
            'marital_status' => 'string',
            'military_status' => 'object',
            'id_number' => 'string',
            'birth_place' => 'string',
            'birth_certificate_number' => 'string',
        ];
    }

    public function structuralRules(): array
    {
        return [
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'gender' => ['nullable', new FormOptionValue('gender')],
            'birth_date' => 'nullable|'.ValidationRules::DATE,
            'marital_status' => ['nullable', new FormOptionValue('marital_status')],
            'military_status' => 'nullable|array',
            'military_status.status' => ['nullable', new FormOptionValue('military_status')],
            'military_status.organization' => 'nullable|string|max:100',
            'military_status.from' => 'nullable|string',
            'military_status.to' => 'nullable|string',
            'military_status.reason' => 'nullable|string|max:255',
            'id_number' => ['nullable', 'string', new IdNumberRule],
            'birth_place' => ['nullable', new FormOptionValue('city', 'province')],
            'birth_certificate_number' => 'nullable|'.ValidationRules::DIGITS_ONLY,
        ];
    }

    public function completionRules(): array
    {
        return [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'gender' => ['required', new FormOptionValue('gender')],
            'birth_date' => 'required|'.ValidationRules::DATE.'|before:today',
            'marital_status' => ['required', new FormOptionValue('marital_status')],
            'id_number' => ['required', 'string', new IdNumberRule],
            'birth_place' => ['required', new FormOptionValue('city', 'province')],
            'birth_certificate_number' => 'required|'.ValidationRules::DIGITS_ONLY,
            'military_status' => 'required_unless:gender,female',
            'military_status.status' => ['required_with:military_status', new FormOptionValue('military_status')],
            'military_status.organization' => 'nullable|string|max:100',
            'military_status.from' => 'nullable|string',
            'military_status.to' => 'nullable|string',
            'military_status.reason' => 'nullable|string|max:255',
        ];
    }

    public function storage(): array
    {
        return [
            'real' => ['first_name', 'last_name'],
            'jsonb' => 'section_personal',
        ];
    }
}
