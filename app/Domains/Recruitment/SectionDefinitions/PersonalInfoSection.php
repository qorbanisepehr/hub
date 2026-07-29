<?php

namespace App\Domains\Recruitment\SectionDefinitions;

class PersonalInfoSection implements SectionDefinition
{
    public function key(): string
    {
        return 'personal_info';
    }

    public function label(): string
    {
        return 'مشخصات فردی';
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
            'military_status' => 'object',
            'national_id' => 'string',
        ];
    }

    public function structuralRules(): array
    {
        return [
            'gender' => 'nullable|in:male,female',
            'blood_group' => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'birth_date' => 'nullable|date',
            'birth_place' => 'nullable|string|max:100',
            'birth_certificate_number' => 'nullable|string|max:20',
            'father_name' => 'nullable|string|max:100',
            'religion' => 'nullable|string|max:50',
            'marital_status' => 'nullable|in:single,married',
            'first_name_en' => 'nullable|string|max:100',
            'last_name_en' => 'nullable|string|max:100',
            'dependents_count' => 'nullable|integer|min:0',
            'children_count' => 'nullable|integer|min:0',
            'spouse_employment_status' => 'nullable|in:employed,housewife',
            'military_status' => 'nullable|array',
            'military_status.status' => 'nullable|in:completed,amrieh,guardian_exemption,medical_exemption,education_exemption,leader_pardon,service_purchase,other',
            'military_status.organization' => 'nullable|string|max:100',
            'military_status.from' => 'nullable|string',
            'military_status.to' => 'nullable|string',
            'military_status.reason' => 'nullable|string|max:255',
            'national_id' => 'nullable|string|size:10',
        ];
    }

    public function completionRules(): array
    {
        return [
            'gender' => 'required|in:male,female',
            'blood_group' => 'required|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'birth_date' => 'required|date|before:today',
            'birth_place' => 'required|string|max:100',
            'birth_certificate_number' => 'required|string|max:20',
            'father_name' => 'required|string|max:100',
            'religion' => 'required|string|max:50',
            'marital_status' => 'required|in:single,married',
            'national_id' => 'required|string|size:10',
            'military_status' => 'required_unless:gender,female',
            'military_status.status' => 'required_with:military_status',
            'military_status.organization' => 'required_with:military_status',
            'military_status.from' => 'required_with:military_status',
            'military_status.to' => 'required_with:military_status',
            'military_status.reason' => 'required_with:military_status',
            'spouse_employment_status' => 'required_if:marital_status,married',
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
}
