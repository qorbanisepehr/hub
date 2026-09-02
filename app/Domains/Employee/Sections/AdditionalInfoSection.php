<?php

namespace App\Domains\Employee\Sections;

use App\Support\Sections\Definitions\AdditionalInfoSection as BaseAdditionalInfoSection;

class AdditionalInfoSection extends BaseAdditionalInfoSection
{
    public function __construct()
    {
        parent::__construct(
            labelKey: 'employee.sections.additional_info',
            realFields: [],
            documentsSectionKey: 'documents',
        );
    }
}
