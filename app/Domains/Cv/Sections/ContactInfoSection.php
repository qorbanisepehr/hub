<?php

namespace App\Domains\Cv\Sections;

use App\Support\Sections\Definitions\ContactInfoSection as BaseContactInfoSection;

class ContactInfoSection extends BaseContactInfoSection
{
    public function __construct()
    {
        parent::__construct(
            labelKey: 'cv.sections.contact_info',
            fields: ['email', 'mobile', 'phone', 'emergency_phone', 'address'],
            realFields: [],
            strictCompletion: false,
        );
    }
}
