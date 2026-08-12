<?php

namespace App\Domains\Cv\Sections;

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
            'military_status' => 'required_unless:gender,زن',
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
