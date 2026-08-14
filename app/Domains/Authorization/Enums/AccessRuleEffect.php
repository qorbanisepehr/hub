<?php

namespace App\Domains\Authorization\Enums;

enum AccessRuleEffect: string
{
    case Allow = 'allow';
    case Deny = 'deny';
}
