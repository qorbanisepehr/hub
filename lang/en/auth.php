<?php

return [
    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',
    'otp_sent' => 'Verification code sent successfully.',
    'otp_already_sent' => 'A verification code was already sent. Please try again shortly.',
    'logout' => 'Logged out successfully.',
    'invalid_otp' => 'The verification code is invalid or expired.',
    'locked' => 'Account locked due to too many failed attempts. Try again in :seconds seconds.',
    'inactive' => 'Your account is inactive. Please contact the administrator.',
    'password_changed' => 'Password changed successfully.',

    'email_otp_subject' => 'Your login verification code',
    'email_otp_greeting' => 'Hello,',
    'email_otp_body' => 'Your verification code to sign in:',
    'email_otp_expiry' => 'This code is valid for :minutes minutes.',
    'email_otp_salutation' => 'Best regards,',

    'sms_otp_body' => 'Your login verification code: :code',
    'channel_unavailable_email' => 'Your account has no registered email address. Please choose the SMS channel.',
    'channel_unavailable_mobile' => 'Your account has no registered mobile number. Please choose the email channel.',
];
