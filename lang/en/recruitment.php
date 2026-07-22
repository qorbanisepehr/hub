<?php

return [
    'questionnaire' => [
        'created' => 'Questionnaire created successfully.',
        'saved' => 'Questionnaire saved.',
        'submitted' => 'Questionnaire submitted successfully.',
        'otp_sent' => 'Verification code sent.',
        'otp_invalid' => 'Invalid verification code.',
        'verified' => 'Verification successful.',
        'not_found' => 'Questionnaire not found.',
        'not_draft' => 'Questionnaire is no longer in draft status.',
    ],
    'validation' => [
        'first_name.required' => 'First name is required.',
        'first_name.max' => 'Maximum 100 characters.',
        'first_name_en.max' => 'Maximum 100 characters.',
        'last_name.required' => 'Last name is required.',
        'last_name.max' => 'Maximum 100 characters.',
        'last_name_en.max' => 'Maximum 100 characters.',
        'email.required' => 'Email is required.',
        'email.email' => 'Invalid email format.',
        'email.max' => 'Maximum 255 characters.',
        'mobile.required' => 'Mobile number is required.',
        'mobile.max' => 'Maximum 15 characters.',
        'current_step.integer' => 'Current step must be a number.',
        'current_step.min' => 'Invalid current step.',
        'current_step.max' => 'Invalid current step.',
        'mobile_otp.required' => 'Mobile verification code is required.',
        'mobile_otp.size' => 'Mobile verification code must be 6 digits.',
        'email_otp.required' => 'Email verification code is required.',
        'email_otp.size' => 'Email verification code must be 6 digits.',
    ],
];
