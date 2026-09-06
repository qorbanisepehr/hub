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
     * employee-only resume, signature sample, and military service card.
     * All live under the `identity-docs` group but are intentionally absent
     * from the questionnaire surface (Q4), so they are declared only here.
     *
     * The military card is optional for now: the plan restricts it to male
     * employees, which the requirement model cannot express yet — the
     * gender-based condition lands with the dynamic-conditions sprint
     * (audit §3.4 / answer).
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
            'military-card' => [
                'required' => false,
                'max_files' => 1,
            ],
        ]);
    }
}
