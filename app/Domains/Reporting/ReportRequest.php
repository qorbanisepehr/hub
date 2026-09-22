<?php

namespace App\Domains\Reporting;

use App\Models\User;

/**
 * Execution context handed to every report: the typed filter/query values
 * plus the authorized actor. HTTP stays outside — controllers translate a
 * FormRequest into this object, so reports remain callable from jobs,
 * commands, and schedulers (plan §16).
 */
final readonly class ReportRequest
{
    /**
     * @param  object|null  $query  The report's typed query object (e.g. AttendanceQuery).
     */
    public function __construct(
        public ?object $query,
        public User $actor,
    ) {}
}
