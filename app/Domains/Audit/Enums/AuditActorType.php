<?php

namespace App\Domains\Audit\Enums;

enum AuditActorType: string
{
    case User = 'user';
    case System = 'system';
}
