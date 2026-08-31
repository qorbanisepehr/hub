<?php

namespace App\Domains\Questionnaire\Sections;

use App\Support\Sections\Definitions\ContactInfoSection as BaseContactInfoSection;

class ContactInfoSection extends BaseContactInfoSection
{
    public function __construct()
    {
        parent::__construct(
            labelKey: 'questionnaire.sections.contact_info',
            fields: ['phone', 'emergency_phone', 'address'],
            realFields: ['phone', 'emergency_phone'],
            strictCompletion: true,
        );
    }
}
