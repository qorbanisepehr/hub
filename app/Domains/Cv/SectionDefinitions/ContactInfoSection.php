<?php

namespace App\Domains\Cv\SectionDefinitions;

use App\Domains\Recruitment\SectionDefinitions\BaseSection;

class ContactInfoSection extends BaseSection
{
    public function key(): string
    {
        return 'contact_info';
    }

    public function label(): string
    {
        return __('cv.sections.contact_info');
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
            // Email stays optional on a CV, but once filled in it must be
            // verified before submit (enforced in the controller).
            'email' => 'nullable|email|max:255',
            'mobile' => 'required|string|max:15|regex:/^09\d{9}$/',
            'phone' => 'nullable|string|max:15|regex:/^0\d{10}$/',
            'emergency_phone' => 'nullable|string|max:15|regex:/^0\d{10}$/',
            'address' => 'nullable|array',
        ];
    }

    public function storage(): array
    {
        return [
            // email/mobile are intentionally excluded: they are staged on save
            // and only committed to the real columns after OTP verification.
            'real' => [],
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
