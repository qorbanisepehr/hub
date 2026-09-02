<?php

namespace App\Domains\Employee\Sections;

use App\Support\Sections\Definitions\ContactInfoSection as BaseContactInfoSection;

class ContactInfoSection extends BaseContactInfoSection
{
    public function __construct()
    {
        parent::__construct(
            labelKey: 'employee.sections.contact_info',
            fields: ['email', 'mobile', 'phone', 'emergency_phone', 'address'],
            realFields: ['email', 'mobile'],
            strictCompletion: true,
        );
    }
}
