<?php

namespace App\Domains\Employee\Sections;

use App\Support\Sections\Definitions\PersonalInfoSection as BasePersonalInfoSection;

class PersonalInfoSection extends BasePersonalInfoSection
{
    public function __construct()
    {
        parent::__construct(labelKey: 'employee.sections.personal_info');
    }

    /**
     * The employee extends the shared applicant document set with the
     * employee-only resume and signature sample. Both live under the
     * `identity-docs` group but are intentionally absent from the
     * questionnaire surface (Q4), so they are declared only here.
     */
    public function documentRequirements(): array
    {
        return array_merge(parent::documentRequirements(), [
            'resume' => [
                'required' => true,
                'max_files' => 1,
            ],
            'signature-sample' => [
                'required' => true,
                'max_files' => 1,
            ],
        ]);
    }
}
