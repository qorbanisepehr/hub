<?php

namespace App\Domains\Cv\Enums;

/**
 * Lifecycle state machine for a CV.
 *
 * draft → submitted → approved
 *                ↑          │
 *                └── rejected (stays visible in the bank; editing flips back to draft)
 */
enum CvStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
