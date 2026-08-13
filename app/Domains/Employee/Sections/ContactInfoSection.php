<?php

namespace App\Domains\Employee\Sections;

use App\Rules\FormOptionValue;
use App\Support\Sections\BaseSection;
use App\Support\ValidationRules;

class ContactInfoSection extends BaseSection
{
    public function key(): string
    {
        return 'contact_info';
    }

    public function label(): string
    {
        return __('employee.sections.contact_info');
    }

    public function fields(): array
    {
        return [
            'email' => 'string',
            'mobile' => 'string',
            'phone' => 'string',
            'emergency_phone' => 'string',
            'address' => 'object',
        ];
    }

    public function structuralRules(): array
    {
        return [
            'email' => 'nullable|'.ValidationRules::EMAIL,
            'mobile' => ['nullable', 'string', 'max:15', ValidationRules::MOBILE_ACCEPTED],
            'phone' => 'nullable|'.ValidationRules::LANDLINE,
            'emergency_phone' => 'nullable|'.ValidationRules::MOBILE_OR_LANDLINE,
            'address' => 'nullable|array',
            'address.postal_code' => 'nullable|'.ValidationRules::POSTAL_CODE,
            'address.province' => ['nullable', new FormOptionValue('province')],
            'address.city' => ['nullable', new FormOptionValue('city')],
            'address.neighborhood' => 'nullable|'.ValidationRules::TEXT.'|max:100',
            'address.address' => 'nullable|'.ValidationRules::TEXT.'|max:500',
            'address.plaque' => 'nullable|'.ValidationRules::TEXT.'|max:10',
            'address.floor' => 'nullable|'.ValidationRules::TEXT.'|max:10',
            'address.unit' => 'nullable|'.ValidationRules::TEXT.'|max:10',
        ];
    }

    public function completionRules(): array
    {
        return [
            'email' => 'required|'.ValidationRules::EMAIL,
            'mobile' => ['required', 'string', 'max:15', ValidationRules::MOBILE_ACCEPTED],
            'phone' => 'required|'.ValidationRules::LANDLINE,
            'emergency_phone' => 'required|'.ValidationRules::MOBILE_OR_LANDLINE,
            'address' => 'required|array',
            'address.postal_code' => 'required|'.ValidationRules::POSTAL_CODE,
            'address.province' => ['required', new FormOptionValue('province')],
            'address.city' => ['required', new FormOptionValue('city')],
            'address.neighborhood' => 'nullable|'.ValidationRules::TEXT.'|max:100',
            'address.address' => 'required|'.ValidationRules::TEXT.'|max:500',
        ];
    }

    public function storage(): array
    {
        return [
            // email/mobile are the only real columns on the employee; the rest
            // of the section lives in the JSONB remainder.
            'real' => ['email', 'mobile'],
            'jsonb' => 'section_contact_address',
        ];
    }

    public function searchMetadata(): array
    {
        return ['address.province', 'address.city'];
    }

    public function prefill(): array
    {
        return [];
    }
}
