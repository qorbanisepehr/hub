<?php

namespace App\Domains\Reporting;

/**
 * The one contract every report honors (plan §15): a stable business key
 * and a run() that turns an execution request into a result object —
 * never a JSON response. Simple contract, implementation-specific logic;
 * this is deliberately not a generic report framework.
 */
interface Report
{
    public function key(): string;

    public function run(ReportRequest $request): ReportResult;
}
