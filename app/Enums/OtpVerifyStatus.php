<?php

namespace App\Enums;

enum OtpVerifyStatus: string
{
    case Success = 'success';

    case Invalid = 'invalid';

    case Expired = 'expired';

    case Locked = 'locked';
}
