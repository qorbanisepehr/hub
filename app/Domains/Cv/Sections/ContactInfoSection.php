<?php

namespace App\Domains\Cv\Sections;

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
            'email' => 'nullable|'.ValidationRules::EMAIL,
            'mobile' => ['nullable', 'string', 'max:15', ValidationRules::MOBILE_ACCEPTED],
            'phone' => 'nullable|'.ValidationRules::LANDLINE,
            'emergency_phone' => 'nullable|'.ValidationRules::MOBILE_OR_LANDLINE,
            'address' => 'nullable|array',
            'address.postal_code' => 'nullable|'.ValidationRules::POSTAL_CODE,
            'address.province' => 'nullable|'.ValidationRules::TEXT.'|max:50',
            'address.city' => 'nullable|'.ValidationRules::TEXT.'|max:50',
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
            // Email stays optional on a CV, but once filled in it must be
            // verified before submit (enforced in the controller).
            'email' => 'nullable|'.ValidationRules::EMAIL,
            'mobile' => ['required', 'string', 'max:15', ValidationRules::MOBILE_ACCEPTED],
            'phone' => 'nullable|'.ValidationRules::LANDLINE,
            'emergency_phone' => 'nullable|'.ValidationRules::MOBILE_OR_LANDLINE,
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
