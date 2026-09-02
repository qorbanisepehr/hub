<?php

namespace App\Domains\Questionnaire\Sections;

use App\Support\Sections\Definitions\AdditionalInfoSection as BaseAdditionalInfoSection;

class AdditionalInfoSection extends BaseAdditionalInfoSection
{
    public function __construct()
    {
        parent::__construct(
            labelKey: 'questionnaire.sections.additional_info',
            realFields: ['has_chronic_disease', 'has_major_surgery', 'has_disability', 'can_travel', 'has_criminal_record'],
            documentsSectionKey: null,
        );
    }
}
