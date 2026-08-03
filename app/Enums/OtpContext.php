<?php

namespace App\Enums;

enum OtpContext: string
{
    case Login = 'login';

    case Register = 'register';

    case VerifyMobile = 'verify_mobile';

    case VerifyEmail = 'verify_email';

    case AccessProtected = 'access_protected';

    case ResetPassword = 'reset_password';
}
