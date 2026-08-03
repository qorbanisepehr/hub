<?php

namespace App\Enums;

enum OtpSendStatus: string
{
    case Sent = 'sent';

    case AlreadySent = 'already_sent';

    case Locked = 'locked';
}
