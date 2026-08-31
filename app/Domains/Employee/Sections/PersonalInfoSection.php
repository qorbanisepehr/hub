<?php

namespace App\Domains\Employee\Sections;

use App\Support\Sections\Definitions\PersonalInfoSection as BasePersonalInfoSection;

class PersonalInfoSection extends BasePersonalInfoSection
{
    public function __construct()
    {
        parent::__construct(labelKey: 'employee.sections.personal_info');
    }
}
