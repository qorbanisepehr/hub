<?php

namespace App\Domains\Questionnaire\Sections;

use App\Support\Sections\Definitions\PersonalInfoSection as BasePersonalInfoSection;

class PersonalInfoSection extends BasePersonalInfoSection
{
    public function __construct()
    {
        parent::__construct(labelKey: 'questionnaire.sections.personal_info');
    }
}
