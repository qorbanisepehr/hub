<?php

namespace App\Domains\Cv\SectionDefinitions;

use App\Domains\Recruitment\SectionDefinitions\BaseSection;
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
        return __('cv.sections.personal_info');
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
            'national_id' => 'string',
            'birth_place' => 'string',
            'birth_certificate_number' => 'string',
        ];
    }

    public function structuralRules(): array
    {
        return [
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'gender' => 'nullable|in:male,female',
            'birth_date' => 'nullable|date',
            'marital_status' => 'nullable|in:single,married',
            'military_status' => 'nullable|array',
            'military_status.status' => 'nullable|in:completed,amrieh,guardian_exemption,medical_exemption,education_exemption,leader_pardon,service_purchase,other',
            'military_status.organization' => 'nullable|string|max:100',
            'military_status.from' => 'nullable|string',
            'military_status.to' => 'nullable|string',
            'military_status.reason' => 'nullable|string|max:255',
            'national_id' => ['nullable', 'string', new NationalIdRule],
            'birth_place' => 'nullable|string|max:100',
            'birth_certificate_number' => 'nullable|string|max:20|regex:/^\d+$/',
        ];
    }

    public function completionRules(): array
    {
        return [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'gender' => 'required|in:male,female',
            'birth_date' => 'required|date|before:today',
            'marital_status' => 'required|in:single,married',
            'national_id' => ['required', 'string', new NationalIdRule],
            'birth_place' => 'required|string|max:100',
            'birth_certificate_number' => 'required|string|max:20|regex:/^\d+$/',
            'military_status' => 'required_unless:gender,female',
            'military_status.status' => 'required_with:military_status',
            'military_status.organization' => 'required_with:military_status',
            'military_status.from' => 'required_with:military_status',
            'military_status.to' => 'required_with:military_status',
            'military_status.reason' => 'required_with:military_status',
        ];
    }

    public function storage(): array
    {
        return [
            'real' => ['first_name', 'last_name'],
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
