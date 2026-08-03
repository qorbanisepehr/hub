<?php

namespace App\Domains\Recruitment\SectionDefinitions;

class ContactInfoSection extends BaseSection
{
    public function key(): string
    {
        return 'contact_info';
    }

    public function label(): string
    {
        return __('recruitment.sections.contact_info');
    }

    public function fields(): array
    {
        return [
            'phone' => 'string',
            'emergency_phone' => 'string',
            'address' => 'object',
        ];
    }

    public function structuralRules(): array
    {
        return [
            'email' => 'nullable|email|max:255',
            'mobile' => 'nullable|string|max:15|regex:/^09\d{9}$/',
            'phone' => 'nullable|string|max:15|regex:/^0\d{10}$/',
            'emergency_phone' => 'nullable|string|max:15|regex:/^0\d{10}$/',
            'address' => 'nullable|array',
            'address.postal_code' => 'nullable|string|max:10',
            'address.province' => 'nullable|string|max:50',
            'address.city' => 'nullable|string|max:50',
            'address.address' => 'nullable|string|max:500',
            'address.plaque' => 'nullable|string|max:10',
            'address.floor' => 'nullable|string|max:10',
            'address.unit' => 'nullable|string|max:10',
        ];
    }

    public function completionRules(): array
    {
        return [
            'email' => 'required|email|max:255',
            'mobile' => 'required|string|max:15|regex:/^09\d{9}$/',
            'phone' => 'required|string|max:15|regex:/^0\d{10}$/',
            'emergency_phone' => 'required|string|max:15|regex:/^0\d{10}$/',
            'address' => 'required|array',
            'address.postal_code' => 'required|string|max:10',
            'address.province' => 'required|string|max:50',
            'address.city' => 'required|string|max:50',
            'address.address' => 'required|string|max:500',
        ];
    }

    public function storage(): array
    {
        return [
            // email/mobile are intentionally excluded: they are staged on save
            // and only committed to the real columns after OTP verification.
            'real' => ['phone', 'emergency_phone'],
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
