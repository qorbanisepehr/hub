<?php

namespace App\Domains\Recruitment\SectionDefinitions;

class ContactInfoSection implements SectionDefinition
{
    public function key(): string
    {
        return 'contact_info';
    }

    public function label(): string
    {
        return 'اطلاعات تماس';
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
            'phone' => 'nullable|string|max:15',
            'emergency_phone' => 'nullable|string|max:15',
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
            'phone' => 'required|string|max:15',
            'emergency_phone' => 'required|string|max:15',
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
            'real' => ['email', 'mobile', 'phone', 'emergency_phone'],
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
