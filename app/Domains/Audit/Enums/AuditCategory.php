<?php

namespace App\Domains\Audit\Enums;

enum AuditCategory: string
{
    case Auth = 'auth';
    case Authorization = 'authorization';
    case Employee = 'employee';
    case Document = 'document';
    case Questionnaire = 'questionnaire';
    case Workflow = 'workflow';
}
